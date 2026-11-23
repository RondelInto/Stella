<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module 4B: stock calculations, recording transactions, and the audit trail.
 * All items and quantities here are TEST DATA.
 */
class InventoryTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private InventoryItem $item;
    private Location $commissary;
    private Location $branch;
    private Location $otherBranch;
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('username', 'admin')->first();
        $this->commissary = Location::where('type', Location::TYPE_COMMISSARY)->first();
        [$this->branch, $this->otherBranch] = Location::where('type', Location::TYPE_BRANCH)->orderBy('id')->take(2)->get();
        $this->inventory = app(InventoryService::class);

        $this->item = InventoryItem::create([
            'inventory_category_id' => InventoryCategory::first()->id,
            'item_code' => 'TEST-001',
            'name' => 'TEST Rice Flour',
            'item_type' => InventoryItem::TYPE_INGREDIENT,
            'unit_of_measure' => 'kg',
            'reorder_level' => 5,
        ]);
    }

    private function employee(string $roleSlug, Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->syncRoleAssignments([Role::where('slug', $roleSlug)->value('id')]);

        return $user->fresh();
    }

    private function onHand(Location $location): string
    {
        return $this->inventory->quantityOnHand($this->item, $location);
    }

    private function postTransaction(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->post(route('inventory-transactions.store'), array_merge([
            'transaction_type' => 'received',
            'location_id' => $this->branch->id,
            'inventory_item_id' => $this->item->id,
            'quantity' => '10',
            'transaction_date' => now()->format('Y-m-d\TH:i'),
            'remarks' => 'TEST',
        ], $overrides));
    }

    // ================= InventoryService (core stock logic) =================

    public function test_received_increases_and_issued_decreases_stock(): void
    {
        $this->inventory->record($this->item, $this->branch, 'received', '10', $this->admin);
        $tx = $this->inventory->record($this->item, $this->branch, 'issued', '3.5', $this->admin);

        $this->assertSame('6.500', $this->onHand($this->branch));
        $this->assertSame('6.500', $tx->balance_after);
        $this->assertSame('3.500', $tx->quantity); // stored as positive
    }

    public function test_decimal_quantities_are_exact(): void
    {
        // 0.1 + 0.2 is NOT exactly 0.3 with normal PHP floats; the service must still get it right.
        $this->inventory->record($this->item, $this->branch, 'received', '0.1', $this->admin);
        $this->inventory->record($this->item, $this->branch, 'received', '0.2', $this->admin);
        $this->inventory->record($this->item, $this->branch, 'issued', '0.3', $this->admin);

        $this->assertSame('0.000', $this->onHand($this->branch));
    }

    public function test_negative_stock_is_blocked_and_nothing_is_saved(): void
    {
        $this->inventory->record($this->item, $this->branch, 'received', '8', $this->admin);

        try {
            $this->inventory->record($this->item, $this->branch, 'issued', '10', $this->admin);
            $this->fail('Issuing more than the stock on hand should be blocked.');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Available: 8 kg, requested: 10 kg', $e->getMessage());
        }

        $this->assertSame('8.000', $this->onHand($this->branch));
        $this->assertSame(1, InventoryTransaction::count());
    }

    public function test_damaged_on_never_stocked_item_is_blocked(): void
    {
        $this->expectException(InsufficientStockException::class);
        $this->inventory->record($this->item, $this->branch, 'damaged', '1', $this->admin);
    }

    public function test_stock_is_tracked_separately_per_location(): void
    {
        $this->inventory->record($this->item, $this->branch, 'received', '10', $this->admin);
        $this->inventory->record($this->item, $this->otherBranch, 'received', '4', $this->admin);

        $this->assertSame('10.000', $this->onHand($this->branch));
        $this->assertSame('4.000', $this->onHand($this->otherBranch));
        $this->assertSame('0.000', $this->onHand($this->commissary));
        $this->assertSame(2, InventoryStock::count()); // one row per item per location
    }

    public function test_count_adjustment_can_go_up_or_down(): void
    {
        $this->inventory->record($this->item, $this->branch, 'received', '10', $this->admin);
        $down = $this->inventory->record($this->item, $this->branch, 'count_adjustment', '-1.5', $this->admin);
        $up = $this->inventory->record($this->item, $this->branch, 'count_adjustment', '0.5', $this->admin);

        $this->assertSame('-1.500', $down->quantity);
        $this->assertSame('0.500', $up->quantity);
        $this->assertSame('9.000', $this->onHand($this->branch));
    }

    public function test_zero_or_negative_quantity_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->inventory->record($this->item, $this->branch, 'received', '0', $this->admin);
    }

    public function test_failure_inside_a_larger_transaction_rolls_back_everything(): void
    {
        // Simulates a future multi-item transfer: item 1 succeeds, item 2 fails -> BOTH are undone.
        $this->inventory->record($this->item, $this->branch, 'received', '5', $this->admin);

        try {
            DB::transaction(function () {
                $this->inventory->record($this->item, $this->branch, 'issued', '2', $this->admin);  // ok
                $this->inventory->record($this->item, $this->branch, 'issued', '99', $this->admin); // fails
            });
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame('5.000', $this->onHand($this->branch));
        $this->assertSame(1, InventoryTransaction::count());
    }

    // ================= Record Transaction page =================

    public function test_staff_can_record_at_their_own_location(): void
    {
        $staff = $this->employee(Role::BRANCH_STAFF, $this->branch);

        $this->postTransaction($staff)->assertRedirect(route('inventory-transactions.create'))->assertSessionHas('success');

        $tx = InventoryTransaction::first();
        $this->assertSame($staff->id, $tx->user_id);
        $this->assertSame($this->branch->id, $tx->location_id);
        $this->assertSame('10.000', $this->onHand($this->branch));
    }

    public function test_staff_location_is_forced_to_their_own_even_if_form_is_changed(): void
    {
        $staff = $this->employee(Role::BRANCH_STAFF, $this->branch);

        $this->postTransaction($staff, ['location_id' => $this->otherBranch->id]);

        $this->assertSame('10.000', $this->onHand($this->branch));
        $this->assertSame('0.000', $this->onHand($this->otherBranch));
    }

    public function test_management_can_record_at_any_location(): void
    {
        $this->postTransaction($this->admin, ['location_id' => $this->otherBranch->id])->assertSessionHasNoErrors();

        $this->assertSame('10.000', $this->onHand($this->otherBranch));
    }

    public function test_over_issuing_shows_a_friendly_error(): void
    {
        $staff = $this->employee(Role::BRANCH_STAFF, $this->branch);

        $this->postTransaction($staff, ['transaction_type' => 'issued', 'quantity' => '3'])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_only_manual_types_can_be_posted(): void
    {
        $this->postTransaction($this->admin, ['transaction_type' => 'transfer_in'])->assertSessionHasErrors('transaction_type');
        $this->postTransaction($this->admin, ['transaction_type' => 'count_adjustment'])->assertSessionHasErrors('transaction_type');
    }

    public function test_future_dates_inactive_items_and_bad_quantities_are_rejected(): void
    {
        $this->postTransaction($this->admin, ['transaction_date' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('transaction_date');
        $this->postTransaction($this->admin, ['quantity' => '-5'])->assertSessionHasErrors('quantity');
        $this->postTransaction($this->admin, ['quantity' => '1.23456'])->assertSessionHasErrors('quantity');

        $this->item->update(['status' => 'inactive']);
        $this->postTransaction($this->admin)->assertSessionHasErrors('inventory_item_id');

        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_roles_without_permission_cannot_record(): void
    {
        $noRole = User::factory()->create(['location_id' => $this->branch->id]);

        $this->postTransaction($noRole)->assertForbidden();
        $this->actingAs($noRole)->get(route('inventory-transactions.create'))->assertForbidden();
    }

    // ================= Stock Levels and History pages =================

    public function test_stock_levels_page_is_scoped_to_own_location(): void
    {
        $this->inventory->record($this->item, $this->branch, 'received', '12', $this->admin);
        $this->inventory->record($this->item, $this->otherBranch, 'received', '77', $this->admin);
        $staff = $this->employee(Role::BRANCH_STAFF, $this->branch);

        $this->actingAs($staff)->get(route('stock-levels.index'))
            ->assertOk()->assertSee('12')->assertDontSee('77');

        // Even if a staff member tries ?location=other, they still only see their own.
        $this->actingAs($staff)->get(route('stock-levels.index', ['location' => $this->otherBranch->id]))
            ->assertDontSee('77');

        $this->actingAs($this->admin)->get(route('stock-levels.index'))
            ->assertSee('12')->assertSee('77');
    }

    public function test_low_stock_filter(): void
    {
        $plenty = InventoryItem::create([
            'inventory_category_id' => InventoryCategory::first()->id,
            'item_code' => 'TEST-002', 'name' => 'TEST Plenty Item',
            'item_type' => InventoryItem::TYPE_INGREDIENT, 'unit_of_measure' => 'kg', 'reorder_level' => 1,
        ]);
        $this->inventory->record($plenty, $this->branch, 'received', '50', $this->admin);
        $this->inventory->record($this->item, $this->branch, 'received', '2', $this->admin); // 2 <= reorder 5

        $this->actingAs($this->admin)->get(route('stock-levels.index', ['location' => $this->branch->id, 'low' => 1]))
            ->assertSee('TEST Rice Flour')->assertDontSee('TEST Plenty Item');
    }

    public function test_history_is_scoped_and_filterable(): void
    {
        $this->inventory->record($this->item, $this->branch, 'received', '5', $this->admin, remarks: 'TEST own branch');
        $this->inventory->record($this->item, $this->otherBranch, 'received', '5', $this->admin, remarks: 'TEST other branch');
        $staff = $this->employee(Role::BRANCH_STAFF, $this->branch);

        $this->actingAs($staff)->get(route('inventory-transactions.index'))
            ->assertOk()->assertSee('TEST own branch')->assertDontSee('TEST other branch');

        $this->actingAs($this->admin)->get(route('inventory-transactions.index', ['location' => $this->otherBranch->id]))
            ->assertSee('TEST other branch')->assertDontSee('TEST own branch');
    }

    public function test_history_search_does_not_leak_other_locations(): void
    {
        // Regression test for the grouped OR in the item search.
        $this->inventory->record($this->item, $this->otherBranch, 'received', '5', $this->admin, remarks: 'TEST hidden');
        $staff = $this->employee(Role::BRANCH_STAFF, $this->branch);

        $this->actingAs($staff)->get(route('inventory-transactions.index', ['search' => 'TEST-001']))
            ->assertDontSee('TEST hidden');
    }

    public function test_dashboard_shows_low_stock_counts(): void
    {
        $staff = $this->employee(Role::BRANCH_STAFF, $this->branch);

        $this->actingAs($staff)->get('/dashboard')
            ->assertOk()
            ->assertSee('Low Stock')
            ->assertSee($this->branch->name);
    }
}
