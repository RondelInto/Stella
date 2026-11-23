<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Module 4A+: CSV item import. Uses the SAMPLE files in docs/samples (TEST DATA only).
 */
class InventoryItemImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->first();
    }

    private function sampleFile(string $name): UploadedFile
    {
        return new UploadedFile(base_path("docs/samples/{$name}"), $name, 'text/csv', null, true);
    }

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'items.csv', 'text/csv', null, true);
    }

    public function test_valid_sample_file_imports_all_rows(): void
    {
        $this->actingAs($this->admin)
            ->post(route('inventory-items.import.store'), ['file' => $this->sampleFile('SAMPLE_inventory_items.csv')])
            ->assertRedirect(route('inventory-items.index'))
            ->assertSessionHas('success', '9 item(s) were imported.');

        $this->assertSame(9, InventoryItem::count());

        $item = InventoryItem::where('item_code', 'SMP-FIN-001')->first();
        $this->assertSame('SAMPLE Palabok Bilao', $item->name);
        $this->assertSame('Cooked Food', $item->category->name);
        $this->assertSame(InventoryItem::TYPE_FINISHED_PRODUCT, $item->item_type);
        $this->assertSame('active', $item->status);
    }

    public function test_error_sample_file_imports_nothing_and_reports_each_bad_row(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('inventory-items.import.store'), ['file' => $this->sampleFile('SAMPLE_inventory_items_with_errors.csv')]);

        $response->assertSessionHas('error');
        $errors = session('import_errors');

        // All-or-nothing: even the 1 valid row is NOT imported.
        $this->assertSame(0, InventoryItem::count());

        // Rows 3-10 are bad (row 1 = header, row 2 = the valid row).
        $this->assertSame([3, 4, 5, 6, 7, 8, 9, 10], array_column($errors, 'row'));

        $messagesByRow = collect($errors)->mapWithKeys(fn ($e) => [$e['row'] => implode(' ', $e['messages'])]);
        $this->assertStringContainsString('Duplicate item code in this file', $messagesByRow[3]);
        $this->assertStringContainsString('letters, numbers', $messagesByRow[4]);
        $this->assertStringContainsString('name', $messagesByRow[5]);
        $this->assertStringContainsString('Unknown category "Desserts"', $messagesByRow[6]);
        $this->assertStringContainsString('item type must be', $messagesByRow[7]);
        $this->assertStringContainsString('at least 0', $messagesByRow[8]);
        $this->assertStringContainsString('must be a number', $messagesByRow[9]);
        $this->assertStringContainsString('unit of measure', $messagesByRow[10]);
    }

    public function test_existing_item_codes_are_rejected(): void
    {
        InventoryItem::create([
            'inventory_category_id' => InventoryCategory::first()->id,
            'item_code' => 'SMP-ING-001',
            'name' => 'TEST Existing',
            'item_type' => InventoryItem::TYPE_INGREDIENT,
            'unit_of_measure' => 'kg',
        ]);

        $this->actingAs($this->admin)
            ->post(route('inventory-items.import.store'), ['file' => $this->sampleFile('SAMPLE_inventory_items.csv')]);

        $this->assertSame(1, InventoryItem::count()); // nothing new imported
        $this->assertStringContainsString('already exists', implode(' ', session('import_errors')[0]['messages']));
        $this->assertSame('TEST Existing', InventoryItem::first()->name); // not overwritten
    }

    public function test_wrong_headers_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('inventory-items.import.store'), [
            'file' => $this->csv("code,item name\nA-1,Something\n"),
        ]);

        $this->assertSame(0, InventoryItem::count());
        $this->assertStringContainsString('headers do not match', session('import_errors')[0]['messages'][0]);
    }

    public function test_excel_bom_blank_lines_and_lowercase_codes_are_handled(): void
    {
        $content = "\xEF\xBB\xBFitem_code,name,category,item_type,unit_of_measure,unit_cost,reorder_level\n"
            ."smp-x-1,SAMPLE Pequeño Item,kakanin,Ingredient,kg,10.50,1\n"
            ."\n"
            ."SMP-X-2,SAMPLE Second,Beverages,finished_product,bottle,0,0\n";

        $this->actingAs($this->admin)
            ->post(route('inventory-items.import.store'), ['file' => $this->csv($content)])
            ->assertSessionHas('success', '2 item(s) were imported.');

        $item = InventoryItem::where('item_code', 'SMP-X-1')->first();
        $this->assertSame('SAMPLE Pequeño Item', $item->name);
        $this->assertSame('Kakanin', $item->category->name);
        $this->assertSame(InventoryItem::TYPE_INGREDIENT, $item->item_type);
    }

    public function test_only_management_can_import(): void
    {
        $staff = User::factory()->create(['location_id' => Location::where('type', 'commissary')->value('id')]);
        $staff->syncRoleAssignments([Role::where('slug', Role::COMMISSARY_STAFF)->value('id')]);

        $this->actingAs($staff)->get(route('inventory-items.import'))->assertForbidden();
        $this->actingAs($staff)
            ->post(route('inventory-items.import.store'), ['file' => $this->sampleFile('SAMPLE_inventory_items.csv')])
            ->assertForbidden();

        $this->assertSame(0, InventoryItem::count());
    }

    public function test_template_download_has_correct_headers(): void
    {
        $response = $this->actingAs($this->admin)->get(route('inventory-items.import.template'));

        $response->assertOk();
        $this->assertStringContainsString(
            'item_code,name,category,item_type,unit_of_measure,unit_cost,reorder_level',
            $response->streamedContent()
        );
    }

    public function test_import_page_renders(): void
    {
        $this->actingAs($this->admin)->get(route('inventory-items.import'))->assertOk()->assertSee('Download Empty Template');
    }
}
