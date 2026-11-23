<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Role;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 5: stock transfers (commissary -> branch). All items and quantities are TEST DATA.
 */
class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $commissaryStaff;
    private User $branchStaff;
    private User $otherBranchStaff;
    private Location $commissary;
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
        $this->commissary = Location::where('type', Location::TYPE_COMMISSARY)->first();
        [$this->branch, $this->otherBranch] = Location::where('type', Location::TYPE_BRANCH)->orderBy('id')->take(2)->get();

        $this->commissaryStaff = $this->employee(Role::COMMISSARY_STAFF, $this->commissary);
        $this->branchStaff = $this->employee(Role::BRANCH_STAFF, $this->branch);
        $this->otherBranchStaff = $this->employee(Role::BRANCH_STAFF, $this->otherBranch);

        $this->inventory = app(InventoryService::class);
        $category = InventoryCategory::first();
        $this->flour = $this->item('TEST-F', 'TEST Flour', $category);
        $this->sugar = $this->item('TEST-S', 'TEST Sugar', $category);

        // Opening commissary stock (TEST DATA).
        $this->inventory->record($this->flour, $this->commissary, 'received', '20', $this->admin);
        $this->inventory->record($this->sugar, $this->commissary, 'received', '10', $this->admin);
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

    private function qty(InventoryItem $item, Location $location): string
    {
        return $this->inventory->quantityOnHand($item, $location);
    }

    private function createTransfer(array $lines = null, ?Location $to = null): StockTransfer
    {
        $lines ??= [
            ['inventory_item_id' => $this->flour->id, 'quantity' => '5'],
            ['inventory_item_id' => $this->sugar->id, 'quantity' => '2.5'],
        ];

        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.store'), [
            'destination_location_id' => ($to ?? $this->branch)->id,
            'transfer_date' => today()->toDateString(),
            'lines' => $lines,
        ])->assertSessionHasNoErrors();

        return StockTransfer::latest('id')->first();
    }

    private function receiveAll(StockTransfer $transfer, User $user, array $overrides = [])
    {
        $received = [];
        foreach ($transfer->details as $detail) {
            $received[$detail->id] = $overrides[$detail->inventory_item_id]
                ?? ['quantity_received' => $detail->quantity_sent, 'remarks' => null];
        }

        return $this->actingAs($user)->post(route('stock-transfers.receive', $transfer), ['received' => $received]);
    }

    // ================= Create =================

    public function test_commissary_staff_create_a_pending_transfer_with_reference_number(): void
    {
        $transfer = $this->createTransfer();

        $this->assertSame(StockTransfer::STATUS_PENDING, $transfer->status);
        $this->assertSame('TR-'.now()->year.'-0001', $transfer->reference_no);
        $this->assertSame($this->commissary->id, $transfer->source_location_id);
        $this->assertSame($this->commissaryStaff->id, $transfer->prepared_by);
        $this->assertSame(2, $transfer->details()->count());

        // Creating moves NO stock.
        $this->assertSame('20.000', $this->qty($this->flour, $this->commissary));
        $this->assertSame(0, InventoryTransaction::where('transaction_type', 'transfer_out')->count());

        $this->assertSame('TR-'.now()->year.'-0002', $this->createTransfer()->reference_no);
    }

    public function test_only_commissary_staff_can_create_transfers(): void
    {
        $payload = ['destination_location_id' => $this->branch->id, 'transfer_date' => today()->toDateString(),
            'lines' => [['inventory_item_id' => $this->flour->id, 'quantity' => '1']]];

        $this->actingAs($this->branchStaff)->post(route('stock-transfers.store'), $payload)->assertForbidden();
        $this->actingAs($this->admin)->post(route('stock-transfers.store'), $payload)->assertForbidden();
        $this->assertSame(0, StockTransfer::count());
    }

    public function test_transfer_validation(): void
    {
        $base = ['destination_location_id' => $this->branch->id, 'transfer_date' => today()->toDateString()];

        // Decision T1: destination must be a branch (not the commissary).
        // array_merge (not +) so the commissary REPLACES the branch in $base.
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.store'), array_merge($base, [
            'destination_location_id' => $this->commissary->id,
            'lines' => [['inventory_item_id' => $this->flour->id, 'quantity' => '1']],
        ]))->assertSessionHasErrors('destination_location_id');

        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.store'), $base + ['lines' => []])
            ->assertSessionHasErrors('lines');

        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.store'), $base + ['lines' => [
            ['inventory_item_id' => $this->flour->id, 'quantity' => '1'],
            ['inventory_item_id' => $this->flour->id, 'quantity' => '2'],
        ]])->assertSessionHasErrors('lines.1.inventory_item_id');

        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.store'), $base + ['lines' => [
            ['inventory_item_id' => $this->flour->id, 'quantity' => '0'],
        ]])->assertSessionHasErrors('lines.0.quantity');

        $this->assertSame(0, StockTransfer::count());
    }

    public function test_pending_transfer_can_be_edited(): void
    {
        $transfer = $this->createTransfer();

        $this->actingAs($this->commissaryStaff)->put(route('stock-transfers.update', $transfer), [
            'destination_location_id' => $this->otherBranch->id,
            'transfer_date' => today()->toDateString(),
            'lines' => [['inventory_item_id' => $this->flour->id, 'quantity' => '7']],
        ])->assertSessionHasNoErrors();

        $transfer->refresh();
        $this->assertSame($this->otherBranch->id, $transfer->destination_location_id);
        $this->assertSame(1, $transfer->details()->count());
        $this->assertSame('7.000', $transfer->details()->value('quantity_sent'));
    }

    // ================= Release =================

    public function test_release_deducts_commissary_stock_once(): void
    {
        $transfer = $this->createTransfer();

        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer))->assertSessionHas('success');

        $transfer->refresh();
        $this->assertSame(StockTransfer::STATUS_RELEASED, $transfer->status);
        $this->assertSame($this->commissaryStaff->id, $transfer->released_by);
        $this->assertNotNull($transfer->released_at);
        $this->assertSame('15.000', $this->qty($this->flour, $this->commissary));
        $this->assertSame('7.500', $this->qty($this->sugar, $this->commissary));
        $this->assertSame('0.000', $this->qty($this->flour, $this->branch)); // not yet received

        $out = InventoryTransaction::where('transaction_type', 'transfer_out')->get();
        $this->assertCount(2, $out);
        $this->assertTrue($out->every(fn ($tx) => $tx->stock_transfer_id === $transfer->id));

        // Releasing again (double-click) is rejected and deducts nothing more.
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer))->assertForbidden();
        $this->assertSame('15.000', $this->qty($this->flour, $this->commissary));
    }

    public function test_release_with_insufficient_stock_releases_nothing(): void
    {
        $transfer = $this->createTransfer([
            ['inventory_item_id' => $this->flour->id, 'quantity' => '5'],  // ok
            ['inventory_item_id' => $this->sugar->id, 'quantity' => '99'], // too much
        ]);

        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer))
            ->assertSessionHas('error');

        $this->assertSame(StockTransfer::STATUS_PENDING, $transfer->fresh()->status);
        $this->assertSame('20.000', $this->qty($this->flour, $this->commissary)); // flour rolled back too
        $this->assertSame(0, InventoryTransaction::where('transaction_type', 'transfer_out')->count());
    }

    public function test_released_transfer_cannot_be_edited_or_cancelled(): void
    {
        $transfer = $this->createTransfer();
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer));

        $this->actingAs($this->commissaryStaff)->get(route('stock-transfers.edit', $transfer))->assertForbidden();
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.cancel', $transfer))->assertForbidden();
        $this->actingAs($this->admin)->post(route('stock-transfers.cancel', $transfer))->assertForbidden();
    }

    // ================= Receive =================

    public function test_destination_branch_receives_full_quantities(): void
    {
        $transfer = $this->createTransfer();
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer));

        $this->receiveAll($transfer->fresh('details'), $this->branchStaff)->assertSessionHas('success');

        $transfer->refresh();
        $this->assertSame(StockTransfer::STATUS_RECEIVED, $transfer->status);
        $this->assertSame($this->branchStaff->id, $transfer->received_by);
        $this->assertSame('5.000', $this->qty($this->flour, $this->branch));
        $this->assertSame('2.500', $this->qty($this->sugar, $this->branch));
        $this->assertFalse($transfer->load('details')->hasDiscrepancy());
        $this->assertSame(2, InventoryTransaction::where('transaction_type', 'transfer_in')->where('stock_transfer_id', $transfer->id)->count());
    }

    public function test_short_receipt_requires_reason_and_records_discrepancy(): void
    {
        $transfer = $this->createTransfer();
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer));
        $transfer = $transfer->fresh('details');

        // Decision T4: reason required when different.
        $this->receiveAll($transfer, $this->branchStaff, [
            $this->flour->id => ['quantity_received' => '4', 'remarks' => ''],
        ])->assertSessionHasErrors();
        $this->assertSame(StockTransfer::STATUS_RELEASED, $transfer->fresh()->status);

        $this->receiveAll($transfer, $this->branchStaff, [
            $this->flour->id => ['quantity_received' => '4', 'remarks' => 'TEST 1 kg spilled'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('4.000', $this->qty($this->flour, $this->branch));
        $detail = $transfer->details()->where('inventory_item_id', $this->flour->id)->first();
        $this->assertSame(-1.0, $detail->discrepancy());
        $this->assertSame('TEST 1 kg spilled', $detail->remarks);
        $this->assertTrue($transfer->fresh()->load('details')->hasDiscrepancy());
    }

    public function test_cannot_receive_more_than_sent(): void
    {
        $transfer = $this->createTransfer();
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer));

        $this->receiveAll($transfer->fresh('details'), $this->branchStaff, [
            $this->flour->id => ['quantity_received' => '6', 'remarks' => 'extra'],
        ])->assertSessionHasErrors();

        $this->assertSame('0.000', $this->qty($this->flour, $this->branch));
    }

    public function test_receiving_zero_moves_no_stock_for_that_item(): void
    {
        $transfer = $this->createTransfer();
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer));

        $this->receiveAll($transfer->fresh('details'), $this->branchStaff, [
            $this->sugar->id => ['quantity_received' => '0', 'remarks' => 'TEST never arrived'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('0.000', $this->qty($this->sugar, $this->branch));
        $this->assertSame(1, InventoryTransaction::where('transaction_type', 'transfer_in')->count());
    }

    public function test_only_the_destination_branch_can_receive(): void
    {
        $transfer = $this->createTransfer();
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer));
        $transfer = $transfer->fresh('details');

        $this->receiveAll($transfer, $this->otherBranchStaff)->assertForbidden();
        $this->receiveAll($transfer, $this->commissaryStaff)->assertForbidden();
        $this->receiveAll($transfer, $this->admin)->assertForbidden();

        $this->assertSame(StockTransfer::STATUS_RELEASED, $transfer->fresh()->status);
    }

    public function test_pending_transfer_cannot_be_received(): void
    {
        $transfer = $this->createTransfer()->load('details');

        $this->receiveAll($transfer, $this->branchStaff)->assertForbidden();
    }

    public function test_received_transfer_cannot_be_received_twice(): void
    {
        $transfer = $this->createTransfer();
        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer));
        $this->receiveAll($transfer->fresh('details'), $this->branchStaff);

        $this->receiveAll($transfer->fresh('details'), $this->branchStaff)->assertForbidden();
        $this->assertSame('5.000', $this->qty($this->flour, $this->branch));
    }

    // ================= Cancel =================

    public function test_pending_transfer_can_be_cancelled_with_reason(): void
    {
        $transfer = $this->createTransfer();

        $this->actingAs($this->admin)->post(route('stock-transfers.cancel', $transfer), ['reason' => 'TEST duplicate'])
            ->assertSessionHas('success');

        $transfer->refresh();
        $this->assertSame(StockTransfer::STATUS_CANCELLED, $transfer->status);
        $this->assertStringContainsString('Cancelled: TEST duplicate', $transfer->remarks);
        $this->assertSame('20.000', $this->qty($this->flour, $this->commissary));
    }

    public function test_branch_staff_cannot_cancel(): void
    {
        $transfer = $this->createTransfer();

        $this->actingAs($this->branchStaff)->post(route('stock-transfers.cancel', $transfer))->assertForbidden();
    }

    // ================= Viewing =================

    public function test_transfers_are_visible_only_to_involved_locations(): void
    {
        $transfer = $this->createTransfer();

        $this->actingAs($this->branchStaff)->get(route('stock-transfers.show', $transfer))->assertOk();
        $this->actingAs($this->commissaryStaff)->get(route('stock-transfers.show', $transfer))->assertOk();
        $this->actingAs($this->admin)->get(route('stock-transfers.show', $transfer))->assertOk();
        $this->actingAs($this->otherBranchStaff)->get(route('stock-transfers.show', $transfer))->assertForbidden();

        $this->actingAs($this->otherBranchStaff)->get(route('stock-transfers.index'))->assertDontSee($transfer->reference_no);
        $this->actingAs($this->branchStaff)->get(route('stock-transfers.index'))->assertSee($transfer->reference_no);
    }

    public function test_pages_render_for_each_step(): void
    {
        $this->actingAs($this->commissaryStaff)->get(route('stock-transfers.create'))->assertOk()->assertSee('TEST Flour');

        $transfer = $this->createTransfer();
        $this->actingAs($this->commissaryStaff)->get(route('stock-transfers.show', $transfer))->assertOk()->assertSee('Release');
        $this->actingAs($this->commissaryStaff)->get(route('stock-transfers.edit', $transfer))->assertOk();

        $this->actingAs($this->commissaryStaff)->post(route('stock-transfers.release', $transfer));
        $this->actingAs($this->branchStaff)->get(route('stock-transfers.show', $transfer))->assertOk()->assertSee('Confirm Receipt');
        $this->actingAs($this->branchStaff)->get(route('stock-transfers.index'))->assertSee('on its way to your branch');
        $this->actingAs($this->branchStaff)->get('/dashboard')->assertSee('To Receive at Your Branch');

        $this->actingAs($this->branchStaff)->get(route('stock-transfers.print', $transfer))
            ->assertOk()->assertSee('Stock Transfer Slip')->assertSee('Received by');
    }
}
