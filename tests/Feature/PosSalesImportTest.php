<?php

namespace Tests\Feature;

use App\Models\AnalysisRun;
use App\Models\ImportedPosSale;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesImportBatch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Module 7: POS sales import (our template, decisions PI1–PI8). All products and prices are TEST DATA.
 */
class PosSalesImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "receipt_no,sale_datetime,product_code,product_name,quantity,unit_price,discount,net_amount\n";

    private User $admin;
    private Location $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->first();
        $this->branch = Location::where('type', Location::TYPE_BRANCH)->orderBy('id')->first();
    }

    private function sampleFile(string $name): UploadedFile
    {
        return new UploadedFile(base_path("docs/samples/{$name}"), $name, 'text/csv', null, true);
    }

    private function csv(string $rows, string $name = 'sales.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, self::HEADER.$rows);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function upload(UploadedFile $file, ?Location $location = null)
    {
        return $this->actingAs($this->admin)->post(route('sales-imports.store'), [
            'location_id' => ($location ?? $this->branch)->id,
            'file' => $file,
        ]);
    }

    private function product(string $name, ?string $code = null): Product
    {
        $item = InventoryItem::create([
            'inventory_category_id' => InventoryCategory::first()->id, 'item_code' => 'TEST-'.uniqid(), 'name' => "TEST {$name}",
            'item_type' => InventoryItem::TYPE_FINISHED_PRODUCT, 'unit_of_measure' => 'pc',
        ]);

        return Product::create(['inventory_item_id' => $item->id, 'pos_product_name' => $name,
            'pos_product_code' => $code, 'selling_price' => 100]);
    }

    // ================= Access =================

    public function test_only_management_can_import(): void
    {
        $this->actingAs($this->admin)->get(route('sales-imports.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('sales-imports.create'))->assertOk();

        foreach ([Role::BRANCH_MANAGER, Role::COMMISSARY_STAFF, Role::BRANCH_STAFF] as $slug) {
            $user = User::factory()->create(['location_id' => $this->branch->id]);
            $user->syncRoleAssignments([Role::where('slug', $slug)->value('id')]);

            $this->actingAs($user)->get(route('sales-imports.index'))->assertForbidden();
            $this->actingAs($user)->post(route('sales-imports.store'), ['location_id' => $this->branch->id])->assertForbidden();
        }
    }

    public function test_template_has_the_headers(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('sales-imports.template'))->assertOk()->streamedContent();

        $this->assertStringContainsString(trim(self::HEADER), $csv);
    }

    // ================= Import =================

    public function test_sample_file_imports_matches_products_and_keeps_raw_data(): void
    {
        $pancit = $this->product('SAMPLE Pancit Bilao', 'SMP-P01');
        $tray = $this->product('SAMPLE Kakanin Tray', 'SMP-P02');
        $tea = $this->product('sample  iced tea'); // no code: matched by name, ignoring case/extra spaces

        $this->upload($this->sampleFile('SAMPLE_pos_sales.csv'))->assertSessionHas('success');

        $batch = SalesImportBatch::sole();
        $this->assertSame($this->branch->id, $batch->location_id);
        $this->assertSame('2026-11-20', $batch->period_start->toDateString());
        $this->assertSame('2026-11-21', $batch->period_end->toDateString());
        $this->assertSame(10, $batch->total_records);
        $this->assertSame('660.00', number_format((float) $batch->sales()->sum('net_amount'), 2, '.', ''));

        $this->assertSame(3, ImportedPosSale::where('product_id', $pancit->id)->count());
        $this->assertSame(2, ImportedPosSale::where('product_id', $tray->id)->count());
        $this->assertSame(4, ImportedPosSale::where('product_id', $tea->id)->count());

        // PI4: the product not in the system is imported as unmatched.
        $unmatched = ImportedPosSale::whereNull('product_id')->sole();
        $this->assertSame('SAMPLE Product Not Yet In System', $unmatched->pos_product_name);

        // Blank net amount = quantity x unit price - discount; the original row is kept unchanged.
        $line = ImportedPosSale::where('pos_receipt_no', 'SMP-R0003')->where('product_id', $tea->id)->sole();
        $this->assertSame('30.00', $line->net_amount);
        $this->assertSame('', $line->raw_data['net_amount']);
        $this->assertSame('SAMPLE Iced Tea', $line->raw_data['product_name']);

        // Excel-style date is accepted.
        $this->assertSame('2026-11-21 18:05:00', ImportedPosSale::where('pos_receipt_no', 'SMP-R0007')->sole()->sale_datetime->toDateTimeString());

        $this->actingAs($this->admin)->get(route('sales-imports.show', $batch))
            ->assertOk()->assertSee('SAMPLE Pancit Bilao')->assertSee('660.00');
    }

    public function test_error_sample_imports_nothing_and_lists_every_bad_row(): void
    {
        $this->upload($this->sampleFile('SAMPLE_pos_sales_with_errors.csv'))->assertSessionHas('error');

        $this->assertSame(0, SalesImportBatch::count());
        $this->assertSame(0, ImportedPosSale::count());

        $errors = collect(session('import_errors'))->mapWithKeys(fn ($e) => [$e['row'] => implode(' ', $e['messages'])]);
        $this->assertSame([3, 4, 5, 6, 7, 8], $errors->keys()->all());
        $this->assertStringContainsString('is not valid', $errors[3]);
        $this->assertStringContainsString('Refunds and voids', $errors[4]);
        $this->assertStringContainsString('no ₱ sign or commas', $errors[5]);
        $this->assertStringContainsString('receipt number', $errors[6]);
        $this->assertStringContainsString('product name', $errors[7]);
        $this->assertStringContainsString('discount is larger', $errors[8]);
    }

    public function test_wrong_headers_and_future_dates_are_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "date,item,qty\n2026-11-01,X,1\n");
        $this->upload(new UploadedFile($path, 'pos.csv', 'text/csv', null, true));
        $this->assertStringContainsString('do not match the template', session('import_errors')[0]['messages'][0]);

        $tomorrow = now()->addDay()->format('Y-m-d H:i');
        $this->upload($this->csv("R1,{$tomorrow},,TEST Item,1,10,,\n"));
        $this->assertStringContainsString('in the future', session('import_errors')[0]['messages'][0]);

        $this->assertSame(0, SalesImportBatch::count());
    }

    public function test_overlapping_dates_for_the_same_location_are_rejected(): void
    {
        $this->upload($this->csv("R1,2026-11-01 09:00,,TEST Item,1,10,,\nR2,2026-11-03 09:00,,TEST Item,1,10,,\n"))
            ->assertSessionHas('success');

        // Overlaps Nov 3 at the same branch -> rejected (PI6).
        $this->upload($this->csv("R3,2026-11-03 18:00,,TEST Item,1,10,,\nR4,2026-11-05 09:00,,TEST Item,1,10,,\n"));
        $this->assertStringContainsString('overlap import', session('import_errors')[0]['messages'][0]);
        $this->assertSame(1, SalesImportBatch::count());

        // Next days at the same branch, or the same days at another location, are fine.
        $this->upload($this->csv("R5,2026-11-04 09:00,,TEST Item,1,10,,\n"))->assertSessionHas('success');
        $other = Location::where('type', Location::TYPE_BRANCH)->orderBy('id')->skip(1)->first();
        $this->upload($this->csv("R6,2026-11-02 09:00,,TEST Item,1,10,,\n"), $other)->assertSessionHas('success');

        $this->assertSame(3, SalesImportBatch::count());

        $this->actingAs($this->admin)->get(route('sales-imports.index'))->assertOk()->assertSee('sales.csv')->assertSee($other->name);
        $this->actingAs($this->admin)->get(route('sales-imports.index', ['location' => $other->id]))
            ->assertOk()->assertSee('Nov 02, 2026')->assertDontSee('Nov 04, 2026');
    }

    public function test_non_csv_files_are_refused(): void
    {
        $this->upload(UploadedFile::fake()->create('sales.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
            ->assertSessionHasErrors('file');
    }

    // ================= Unmatched products (PI4) =================

    public function test_unmatched_rows_can_be_linked_to_a_product(): void
    {
        $this->upload($this->csv("R1,2026-11-01 09:00,X9,TEST Turon,2,15,,\nR2,2026-11-01 10:00,X9,TEST Turon,1,15,,\nR3,2026-11-01 11:00,,TEST Turon,1,15,,\n"));
        $this->assertSame(3, ImportedPosSale::whereNull('product_id')->count());

        $this->actingAs($this->admin)->get(route('sales-imports.unmatched'))->assertOk()->assertSee('TEST Turon')->assertSee('X9');

        $turon = $this->product('Turon (Banana Roll)');
        $this->actingAs($this->admin)->post(route('sales-imports.link'), [
            'pos_product_name' => 'TEST Turon', 'pos_product_code' => 'X9', 'product_id' => $turon->id,
        ])->assertSessionHas('success');

        // Only the group with code X9 is linked; the row without a code is a separate group.
        $this->assertSame(2, ImportedPosSale::where('product_id', $turon->id)->count());
        $this->assertSame(1, ImportedPosSale::whereNull('product_id')->count());
    }

    // ================= Delete (PI7) =================

    public function test_import_can_be_deleted_until_an_analysis_uses_it(): void
    {
        $this->upload($this->csv("R1,2026-11-01 09:00,,TEST Item,1,10,,\n"));
        $this->upload($this->csv("R2,2026-11-02 09:00,,TEST Item,1,10,,\n"));
        [$free, $used] = SalesImportBatch::orderBy('id')->get();

        $run = AnalysisRun::create(['run_by' => $this->admin->id, 'analysis_type' => AnalysisRun::TYPE_FREQUENCY,
            'period_start' => '2026-11-02', 'period_end' => '2026-11-02', 'status' => 'completed']);
        $run->batches()->attach($used->id);

        $this->actingAs($this->admin)->delete(route('sales-imports.destroy', $used))->assertSessionHas('error');
        $this->assertModelExists($used);

        $this->actingAs($this->admin)->delete(route('sales-imports.destroy', $free))->assertRedirect(route('sales-imports.index'));
        $this->assertModelMissing($free);
        $this->assertSame(1, ImportedPosSale::count());
    }
}
