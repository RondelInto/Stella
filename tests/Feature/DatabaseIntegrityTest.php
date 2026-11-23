<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Product;
use App\Models\Role;
use App\Models\SopRecord;
use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Phase 2 tests: seeders, relationships, and database rules.
 * All data created here is TEST DATA and only exists in the testing database.
 */
class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function makeItem(): InventoryItem
    {
        return InventoryItem::create([
            'inventory_category_id' => InventoryCategory::first()->id,
            'item_code' => 'TEST-001',
            'name' => 'TEST ITEM',
            'item_type' => InventoryItem::TYPE_INGREDIENT,
            'unit_of_measure' => 'kg',
            'unit_cost' => 50,
        ]);
    }

    public function test_seeders_create_locations_roles_categories_and_admin(): void
    {
        $this->assertSame(4, Location::count());
        $this->assertSame(1, Location::where('type', Location::TYPE_COMMISSARY)->count());
        $this->assertSame(4, Role::count());
        $this->assertSame(3, InventoryCategory::count());

        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertNull($admin->location_id);
    }

    public function test_seeders_can_run_twice_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Location::count());
        $this->assertSame(4, Role::count());
        $this->assertSame(1, User::where('username', 'admin')->count());
    }

    public function test_inactive_role_assignment_is_ignored(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::BRANCH_STAFF)->value('id'), ['is_active' => false]);

        $this->assertFalse($user->fresh()->hasRole(Role::BRANCH_STAFF));
    }

    public function test_an_item_can_only_have_one_stock_row_per_location(): void
    {
        $item = $this->makeItem();
        $location = Location::first();

        InventoryStock::create(['location_id' => $location->id, 'inventory_item_id' => $item->id]);

        $this->expectException(QueryException::class);
        InventoryStock::create(['location_id' => $location->id, 'inventory_item_id' => $item->id]);
    }

    public function test_location_with_users_cannot_be_deleted(): void
    {
        $location = Location::first();
        User::factory()->create(['location_id' => $location->id]);

        $this->expectException(QueryException::class);
        $location->delete();
    }

    public function test_inventory_transactions_cannot_be_edited_or_deleted(): void
    {
        $tx = InventoryTransaction::create([
            'inventory_item_id' => $this->makeItem()->id,
            'location_id' => Location::first()->id,
            'user_id' => User::first()->id,
            'transaction_type' => InventoryTransaction::RECEIVED,
            'quantity' => 10,
            'balance_after' => 10,
            'transaction_date' => now(),
        ]);

        try {
            $tx->update(['quantity' => 99]);
            $this->fail('Editing a transaction should not be allowed.');
        } catch (LogicException) {
            // expected
        }

        $this->expectException(LogicException::class);
        $tx->delete();
    }

    public function test_stock_transfer_relationships(): void
    {
        $commissary = Location::where('type', Location::TYPE_COMMISSARY)->first();
        $branch = Location::where('type', Location::TYPE_BRANCH)->first();
        $item = $this->makeItem();

        $transfer = StockTransfer::create([
            'reference_no' => 'TR-TEST-0001',
            'source_location_id' => $commissary->id,
            'destination_location_id' => $branch->id,
            'prepared_by' => User::first()->id,
            'transfer_date' => now()->toDateString(),
        ]);
        $transfer->details()->create([
            'inventory_item_id' => $item->id,
            'quantity_sent' => 20,
            'quantity_received' => 18,
        ]);

        $transfer->refresh();
        $this->assertSame(StockTransfer::STATUS_PENDING, $transfer->status);
        $this->assertTrue($transfer->sourceLocation->is($commissary));
        $this->assertTrue($transfer->destinationLocation->is($branch));
        $this->assertSame(-2.0, $transfer->details->first()->discrepancy());
    }

    public function test_product_cost_comes_from_linked_inventory_item(): void
    {
        $item = $this->makeItem();
        $product = Product::create([
            'inventory_item_id' => $item->id,
            'pos_product_name' => 'TEST PRODUCT',
            'selling_price' => 120,
        ]);

        $this->assertSame('50.00', $product->inventoryItem->unit_cost);
    }

    public function test_one_sop_can_be_assigned_to_many_roles(): void
    {
        $sop = SopRecord::create([
            'sop_code' => 'SOP-TEST-001',
            'title' => 'TEST SOP',
            'category' => 'Inventory',
            'content' => 'TEST CONTENT',
            'created_by' => User::first()->id,
        ]);

        $sop->roles()->attach(Role::whereIn('slug', [Role::BRANCH_MANAGER, Role::BRANCH_STAFF])->pluck('id'));

        $this->assertCount(2, $sop->roles);
        $this->assertTrue(Role::where('slug', Role::BRANCH_STAFF)->first()->sopRecords->contains($sop));
    }
}
