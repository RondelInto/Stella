<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 6: products, production records, and production planning. All data is TEST DATA.
 */
class ProductionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $commissaryStaff;
    private User $branchManager;
    private User $branchStaff;
    private Location $commissary;
    private Location $branch;
    private Location $otherBranch;
    private InventoryItem $noodles;
    private InventoryItem $sauce;
    private InventoryItem $palabokItem;
    private Product $palabok;
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('username', 'admin')->first();
        $this->commissary = Location::where('type', Location::TYPE_COMMISSARY)->first();
        [$this->branch, $this->otherBranch] = Location::where('type', Location::TYPE_BRANCH)->orderBy('id')->take(2)->get();
        $this->commissaryStaff = $this->employee(Role::COMMISSARY_STAFF, $this->commissary);
        $this->branchManager = $this->employee(Role::BRANCH_MANAGER, $this->branch);
        $this->branchStaff = $this->employee(Role::BRANCH_STAFF, $this->branch);
        $this->inventory = app(InventoryService::class);

        $cooked = InventoryCategory::where('name', 'Cooked Food')->first();
        $this->noodles = $this->item('TEST-ING-1', 'TEST Rice Noodles', InventoryItem::TYPE_INGREDIENT, 'kg', $cooked);
        $this->sauce = $this->item('TEST-PRP-1', 'TEST Palabok Sauce', InventoryItem::TYPE_IN_PREPARATION, 'liter', $cooked);
        $this->palabokItem = $this->item('TEST-FIN-1', 'TEST Palabok Bilao', InventoryItem::TYPE_FINISHED_PRODUCT, 'bilao', $cooked, '120.00');

        $this->palabok = Product::create([
            'inventory_item_id' => $this->palabokItem->id,
            'pos_product_name' => 'TEST Palabok Bilao',
            'selling_price' => '350.00',
        ]);

        $this->inventory->record($this->noodles, $this->commissary, 'received', '20', $this->admin);
        $this->inventory->record($this->sauce, $this->commissary, 'received', '10', $this->admin);
    }

    private function item(string $code, string $name, string $type, string $unit, InventoryCategory $category, string $cost = '0'): InventoryItem
    {
        return InventoryItem::create([
            'inventory_category_id' => $category->id, 'item_code' => $code, 'name' => $name,
            'item_type' => $type, 'unit_of_measure' => $unit, 'unit_cost' => $cost,
        ]);
    }

    private function employee(string $roleSlug, Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->syncRoleAssignments([Role::where('slug', $roleSlug)->value('id')]);

        return $user->fresh();
    }

    private function qty(InventoryItem $item, Location $location): string
    {
        return $this->inventory->quantityOnHand($item, $location);
    }

    private function recordProduction(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->post(route('production.store'), array_merge([
            'product_id' => $this->palabok->id,
            'quantity_produced' => '30',
            'production_date' => today()->toDateString(),
            'ingredients' => [
                ['inventory_item_id' => $this->noodles->id, 'quantity' => '12'],
                ['inventory_item_id' => $this->sauce->id, 'quantity' => '6.5'],
            ],
        ], $overrides));
    }

    // ================= Products =================

    public function test_admin_can_create_a_product_linked_to_a_finished_item(): void
    {
        $item = $this->item('TEST-FIN-2', 'TEST Kutsinta Tray', InventoryItem::TYPE_FINISHED_PRODUCT, 'tray', InventoryCategory::first());

        $this->actingAs($this->admin)->post(route('products.store'), [
            'pos_product_name' => 'TEST Kutsinta Tray',
            'pos_product_code' => 'K-01',
            'selling_price' => '200',
            'inventory_item_id' => $item->id,
        ])->assertSessionHasNoErrors();

        $product = Product::where('pos_product_code', 'K-01')->first();
        $this->assertSame($item->id, $product->inventory_item_id);
        $this->assertSame('active', $product->status);
    }

    public function test_product_must_link_to_an_unlinked_finished_product_item(): void
    {
        $payload = ['pos_product_name' => 'TEST X', 'selling_price' => '1'];

        // Ingredient item -> rejected.
        $this->actingAs($this->admin)->post(route('products.store'), $payload + ['inventory_item_id' => $this->noodles->id])
            ->assertSessionHasErrors('inventory_item_id');

        // Already linked to TEST Palabok -> rejected.
        $this->actingAs($this->admin)->post(route('products.store'), $payload + ['inventory_item_id' => $this->palabokItem->id])
            ->assertSessionHasErrors('inventory_item_id');
    }

    public function test_only_management_manages_products(): void
    {
        $this->actingAs($this->commissaryStaff)->get(route('products.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('products.index'))->assertOk()->assertSee('TEST Palabok Bilao');
    }

    // ================= Recording production =================

    public function test_production_deducts_ingredients_and_adds_finished_product(): void
    {
        $this->recordProduction($this->commissaryStaff)->assertSessionHasNoErrors()->assertSessionHas('success');

        $record = ProductionRecord::sole();
        $this->assertSame($this->commissary->id, $record->location_id);
        $this->assertSame($this->commissaryStaff->id, $record->user_id);
        $this->assertSame('30.000', $record->quantity_produced);
        $this->assertSame(2, $record->details()->count());

        $this->assertSame('8.000', $this->qty($this->noodles, $this->commissary));
        $this->assertSame('3.500', $this->qty($this->sauce, $this->commissary));
        $this->assertSame('30.000', $this->qty($this->palabokItem, $this->commissary));

        // Audit trail: 2 production_use + 1 production_output, all linked to the record.
        $this->assertSame(2, InventoryTransaction::where('transaction_type', 'production_use')->where('production_record_id', $record->id)->count());
        $this->assertSame(1, InventoryTransaction::where('transaction_type', 'production_output')->where('production_record_id', $record->id)->count());
    }

    public function test_insufficient_ingredient_saves_nothing(): void
    {
        $this->recordProduction($this->commissaryStaff, ['ingredients' => [
            ['inventory_item_id' => $this->noodles->id, 'quantity' => '5'],  // ok
            ['inventory_item_id' => $this->sauce->id, 'quantity' => '99'],   // too much
        ]])->assertSessionHas('error');

        $this->assertSame(0, ProductionRecord::count());
        $this->assertSame('20.000', $this->qty($this->noodles, $this->commissary));
        $this->assertSame('0.000', $this->qty($this->palabokItem, $this->commissary));
    }

    public function test_at_least_one_ingredient_is_required(): void
    {
        $this->recordProduction($this->commissaryStaff, ['ingredients' => []])->assertSessionHasErrors('ingredients');
        $this->assertSame(0, ProductionRecord::count());
    }

    public function test_finished_products_cannot_be_used_as_ingredients(): void
    {
        $this->recordProduction($this->commissaryStaff, ['ingredients' => [
            ['inventory_item_id' => $this->palabokItem->id, 'quantity' => '1'],
        ]])->assertSessionHasErrors('ingredients.0.inventory_item_id');
    }

    public function test_validation_rejects_future_dates_duplicates_and_inactive_products(): void
    {
        $this->recordProduction($this->commissaryStaff, ['production_date' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('production_date');

        $this->recordProduction($this->commissaryStaff, ['ingredients' => [
            ['inventory_item_id' => $this->noodles->id, 'quantity' => '1'],
            ['inventory_item_id' => $this->noodles->id, 'quantity' => '2'],
        ]])->assertSessionHasErrors('ingredients.1.inventory_item_id');

        $this->palabok->update(['status' => 'inactive']);
        $this->recordProduction($this->commissaryStaff)->assertSessionHasErrors('product_id');

        $this->assertSame(0, ProductionRecord::count());
    }

    public function test_only_commissary_staff_can_record_production(): void
    {
        $this->recordProduction($this->admin)->assertForbidden();          // confirmed permission P5
        $this->recordProduction($this->branchManager)->assertForbidden();
        $this->actingAs($this->branchStaff)->get(route('production.create'))->assertForbidden();

        $this->assertSame(0, ProductionRecord::count());
    }

    // ================= Viewing =================

    public function test_history_is_visible_to_admin_branch_manager_and_commissary_but_not_branch_staff(): void
    {
        $this->recordProduction($this->commissaryStaff);
        $record = ProductionRecord::sole();

        foreach ([$this->admin, $this->branchManager, $this->commissaryStaff] as $user) {
            $this->actingAs($user)->get(route('production.index'))->assertOk()->assertSee('TEST Palabok Bilao');
            $this->actingAs($user)->get(route('production.show', $record))->assertOk()->assertSee('TEST Rice Noodles');
        }

        $this->actingAs($this->branchStaff)->get(route('production.index'))->assertForbidden(); // confirmed permission P4
    }

    public function test_planning_shows_all_locations_to_commissary_and_admin_but_own_branch_to_managers(): void
    {
        $this->recordProduction($this->commissaryStaff);
        // Send 4 bilao to the branch so it has leftover stock (TEST DATA).
        $this->inventory->record($this->palabokItem, $this->branch, 'received', '4', $this->admin);

        foreach ([$this->admin, $this->commissaryStaff] as $user) {
            $this->actingAs($user)->get(route('production.planning'))
                ->assertOk()
                ->assertSee($this->commissary->name)
                ->assertSee($this->otherBranch->name)
                ->assertSee('Total Remaining');
        }

        $this->actingAs($this->branchManager)->get(route('production.planning'))
            ->assertOk()
            ->assertSee($this->branch->name)
            ->assertDontSee($this->otherBranch->name)
            ->assertDontSee('Total Remaining');
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->commissaryStaff)->get(route('production.create'))
            ->assertOk()->assertSee('TEST Rice Noodles')->assertDontSee('[0 bilao]');
        $this->actingAs($this->admin)->get(route('products.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('products.edit', $this->palabok))->assertOk()->assertSee('TEST Palabok Bilao');

        $this->recordProduction($this->commissaryStaff);
        $this->actingAs($this->commissaryStaff)->get('/dashboard')->assertOk()->assertSee('Batches Recorded Today');
    }
}
