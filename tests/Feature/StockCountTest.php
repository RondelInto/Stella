<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Role;
use App\Models\StockCount;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 4C: blind stock counts, variances, automatic correction, opening balances.
 * All items and quantities here are TEST DATA.
 */
class StockCountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Location $branch;
    private Location $otherBranch;
    private InventoryItem $flour;
    private InventoryItem $sugar;
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('username', 'admin')->first();
        [$this->branch, $this->otherBranch] = Location::where('type', Location::TYPE_BRANCH)->orderBy('id')->take(2)->get();
        $this->staff = $this->employee(Role::BRANCH_STAFF, $this->branch);
        $this->inventory = app(InventoryService::class);

        $kakanin = InventoryCategory::where('name', 'Kakanin')->first();
        $this->flour = $this->item('TEST-F', 'TEST Flour', $kakanin);
        $this->sugar = $this->item('TEST-S', 'TEST Sugar', $kakanin);
    }

    private function item(string $code, string $name, InventoryCategory $category): InventoryItem
    {
        return InventoryItem::create([
            'inventory_category_id' => $category->id, 'item_code' => $code, 'name' => $name,
            'item_type' => InventoryItem::TYPE_INGREDIENT, 'unit_of_measure' => 'kg',
        ]);
    }

    private function employee(string $roleSlug, Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->syncRoleAssignments([Role::where('slug', $roleSlug)->value('id')]);

        return $user->fresh();
    }

    private function startCount(User $user, array $payload = []): StockCount
    {
        $this->actingAs($user)->post(route('stock-counts.store'), $payload + [
            'count_date' => today()->toDateString(),
            'items' => [$this->flour->id, $this->sugar->id],
        ])->assertSessionHasNoErrors();

        return StockCount::latest('id')->first();
    }

    /** Enter quantities [item_id => qty] and save or complete. */
    private function submit(User $user, StockCount $count, array $quantities, string $action)
    {
        $counts = [];
        foreach ($count->details as $detail) {
            $counts[$detail->id] = $quantities[$detail->inventory_item_id] ?? '';
        }

        return $this->actingAs($user)->put(route('stock-counts.update', $count), ['counts' => $counts, 'action' => $action]);
    }

    // ---- Starting a count ----

    public function test_staff_can_start_a_count_at_their_own_location(): void
    {
        $count = $this->startCount($this->staff);

        $this->assertSame($this->branch->id, $count->location_id);
        $this->assertSame(StockCount::STATUS_DRAFT, $count->status);
        $this->assertSame(2, $count->details()->count());
        $this->assertSame(0, InventoryTransaction::count()); // starting a count changes nothing
    }

    public function test_count_can_select_whole_categories(): void
    {
        $count = $this->startCount($this->staff, [
            'items' => [],
            'categories' => [InventoryCategory::where('name', 'Kakanin')->value('id')],
        ]);

        $this->assertEqualsCanonicalizing(
            [$this->flour->id, $this->sugar->id],
            $count->details->pluck('inventory_item_id')->all()
        );
    }

    public function test_count_needs_at_least_one_item_and_no_future_date(): void
    {
        $this->actingAs($this->staff)->post(route('stock-counts.store'), ['count_date' => today()->toDateString()])
            ->assertSessionHasErrors('items');

        $this->actingAs($this->staff)->post(route('stock-counts.store'), [
            'count_date' => today()->addDay()->toDateString(), 'items' => [$this->flour->id],
        ])->assertSessionHasErrors('count_date');
    }

    public function test_management_cannot_start_counts(): void
    {
        // Confirmed permission: record-stock-counts is for location staff only.
        $this->actingAs($this->admin)->get(route('stock-counts.create'))->assertForbidden();
    }

    // ---- Blind counting ----

    public function test_draft_entry_form_does_not_reveal_system_quantity(): void
    {
        $this->inventory->record($this->flour, $this->branch, 'received', '123.456', $this->admin);
        $count = $this->startCount($this->staff);

        $this->actingAs($this->staff)->get(route('stock-counts.show', $count))
            ->assertOk()
            ->assertSee('Blind count')
            ->assertDontSee('123.456')
            ->assertDontSee('123.5');
    }

    public function test_progress_can_be_saved_and_continued(): void
    {
        $count = $this->startCount($this->staff);

        $this->submit($this->staff, $count, [$this->flour->id => '4.5'], 'save')->assertSessionHas('success');

        $this->assertSame(StockCount::STATUS_DRAFT, $count->fresh()->status);
        $this->assertSame('4.500', $count->details()->where('inventory_item_id', $this->flour->id)->value('counted_quantity'));
        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_cannot_complete_until_every_item_is_counted(): void
    {
        $count = $this->startCount($this->staff);

        $this->submit($this->staff, $count, [$this->flour->id => '4'], 'complete')->assertSessionHas('error');

        $this->assertSame(StockCount::STATUS_DRAFT, $count->fresh()->status);
    }

    // ---- Completing: variances and automatic correction ----

    public function test_completing_records_variances_and_corrects_stock(): void
    {
        $this->inventory->record($this->flour, $this->branch, 'received', '10', $this->admin);
        $this->inventory->record($this->sugar, $this->branch, 'received', '5', $this->admin);
        $count = $this->startCount($this->staff);

        // Flour: 8.5 counted (1.5 missing). Sugar: 5 counted (matches).
        $this->submit($this->staff, $count, [$this->flour->id => '8.5', $this->sugar->id => '5'], 'complete')
            ->assertSessionHas('success');

        $count->refresh();
        $this->assertSame(StockCount::STATUS_COMPLETED, $count->status);
        $this->assertNotNull($count->completed_at);

        $flourDetail = $count->details()->where('inventory_item_id', $this->flour->id)->first();
        $this->assertSame('10.000', $flourDetail->system_quantity);
        $this->assertSame('-1.500', $flourDetail->variance);
        $this->assertSame('0.000', $count->details()->where('inventory_item_id', $this->sugar->id)->value('variance'));

        // Stock corrected to the counted amount.
        $this->assertSame('8.500', $this->inventory->quantityOnHand($this->flour, $this->branch));
        $this->assertSame('5.000', $this->inventory->quantityOnHand($this->sugar, $this->branch));

        // Only ONE adjustment (sugar matched), linked to the count, with the user and signed quantity.
        $adjustment = InventoryTransaction::where('transaction_type', 'count_adjustment')->sole();
        $this->assertSame($this->flour->id, $adjustment->inventory_item_id);
        $this->assertSame('-1.500', $adjustment->quantity);
        $this->assertSame($count->id, $adjustment->stock_count_id);
        $this->assertSame($this->staff->id, $adjustment->user_id);
    }

    public function test_first_count_sets_opening_balances(): void
    {
        $count = $this->startCount($this->staff);

        $this->submit($this->staff, $count, [$this->flour->id => '25', $this->sugar->id => '0'], 'complete');

        $this->assertSame('25.000', $this->inventory->quantityOnHand($this->flour, $this->branch));
        $this->assertSame('+25', '+'.rtrim(rtrim($count->details()->where('inventory_item_id', $this->flour->id)->value('variance'), '0'), '.'));
        $this->assertSame(1, InventoryTransaction::count()); // sugar 0 = 0, no adjustment needed
    }

    public function test_system_quantity_is_taken_at_completion_time(): void
    {
        $count = $this->startCount($this->staff);
        $this->submit($this->staff, $count, [$this->flour->id => '7', $this->sugar->id => '0'], 'save');

        // Stock received AFTER the count was started but BEFORE it was completed.
        $this->inventory->record($this->flour, $this->branch, 'received', '7', $this->admin);

        $this->submit($this->staff, $count, [$this->flour->id => '7', $this->sugar->id => '0'], 'complete');

        $this->assertSame('7.000', $count->details()->where('inventory_item_id', $this->flour->id)->value('system_quantity'));
        $this->assertSame('0.000', $count->details()->where('inventory_item_id', $this->flour->id)->value('variance'));
    }

    public function test_a_completed_count_cannot_be_completed_or_edited_again(): void
    {
        $count = $this->startCount($this->staff);
        $this->submit($this->staff, $count, [$this->flour->id => '3', $this->sugar->id => '0'], 'complete');
        $count->refresh();

        $this->submit($this->staff, $count, [$this->flour->id => '99', $this->sugar->id => '0'], 'complete')->assertForbidden();

        $this->assertSame('3.000', $this->inventory->quantityOnHand($this->flour, $this->branch));
        $this->assertSame(1, InventoryTransaction::count());
    }

    public function test_negative_counts_are_rejected(): void
    {
        $count = $this->startCount($this->staff);

        $this->submit($this->staff, $count, [$this->flour->id => '-1', $this->sugar->id => '0'], 'complete')
            ->assertSessionHasErrors();

        $this->assertSame(StockCount::STATUS_DRAFT, $count->fresh()->status);
    }

    // ---- Location scoping ----

    public function test_staff_from_another_branch_cannot_view_or_edit_the_count(): void
    {
        $count = $this->startCount($this->staff);
        $outsider = $this->employee(Role::BRANCH_STAFF, $this->otherBranch);

        $this->actingAs($outsider)->get(route('stock-counts.show', $count))->assertForbidden();
        $this->submit($outsider, $count, [$this->flour->id => '1', $this->sugar->id => '1'], 'complete')->assertForbidden();
        $this->actingAs($outsider)->delete(route('stock-counts.destroy', $count))->assertForbidden();
    }

    public function test_management_sees_draft_without_quantities_and_results_after_completion(): void
    {
        $this->inventory->record($this->flour, $this->branch, 'received', '10', $this->admin);
        $count = $this->startCount($this->staff);

        $this->actingAs($this->admin)->get(route('stock-counts.show', $count))
            ->assertOk()->assertSee('still in progress');

        $this->submit($this->staff, $count, [$this->flour->id => '9', $this->sugar->id => '0'], 'complete');

        $this->actingAs($this->admin)->get(route('stock-counts.show', $count))
            ->assertOk()->assertSee('did not match the system');
    }

    // ---- Draft deletion and discrepancy list ----

    public function test_draft_can_be_deleted_without_changing_stock(): void
    {
        $count = $this->startCount($this->staff);

        $this->actingAs($this->staff)->delete(route('stock-counts.destroy', $count))
            ->assertRedirect(route('stock-counts.index'));

        $this->assertNull($count->fresh());
        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_discrepancy_list_shows_only_mismatches_in_accessible_locations(): void
    {
        $this->inventory->record($this->flour, $this->branch, 'received', '10', $this->admin);
        $this->inventory->record($this->sugar, $this->branch, 'received', '5', $this->admin);
        $count = $this->startCount($this->staff);
        $this->submit($this->staff, $count, [$this->flour->id => '8', $this->sugar->id => '5'], 'complete');

        $this->actingAs($this->staff)->get(route('stock-counts.discrepancies'))
            ->assertOk()->assertSee('TEST Flour')->assertDontSee('TEST Sugar');

        $outsider = $this->employee(Role::BRANCH_STAFF, $this->otherBranch);
        $this->actingAs($outsider)->get(route('stock-counts.discrepancies'))->assertDontSee('TEST Flour');

        $this->actingAs($this->admin)->get(route('stock-counts.discrepancies'))->assertSee('TEST Flour');
    }

    public function test_counts_index_renders_for_staff_and_management(): void
    {
        $this->startCount($this->staff);

        $this->actingAs($this->staff)->get(route('stock-counts.index'))->assertOk()->assertSee('In progress');
        $this->actingAs($this->admin)->get(route('stock-counts.index'))->assertOk()->assertSee($this->branch->name);
    }
}
