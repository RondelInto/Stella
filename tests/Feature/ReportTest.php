<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductionService;
use App\Services\StockTransferService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Module 10: reports. All quantities and costs are TEST DATA.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $commissaryStaff;
    private User $branchManager;
    private User $otherBranchManager;
    private Location $commissary;
    private Location $branch;
    private Location $otherBranch;
    private InventoryItem $flour;
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
        $this->otherBranchManager = $this->employee(Role::BRANCH_MANAGER, $this->otherBranch);
        $this->inventory = app(InventoryService::class);

        $this->flour = InventoryItem::create([
            'inventory_category_id' => InventoryCategory::first()->id, 'item_code' => 'TEST-F', 'name' => 'TEST Flour',
            'item_type' => InventoryItem::TYPE_INGREDIENT, 'unit_of_measure' => 'kg', 'unit_cost' => '55.00',
        ]);
    }

    private function employee(string $roleSlug, Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->syncRoleAssignments([Role::where('slug', $roleSlug)->value('id')]);

        return $user->fresh();
    }

    private function record(Location $location, string $type, string $qty, string $date): void
    {
        $this->inventory->record($this->flour, $location, $type, $qty, $this->admin, Carbon::parse($date));
    }

    // ================= Access =================

    public function test_access_by_role(): void
    {
        $this->actingAs($this->admin)->get(route('reports.index'))->assertOk()->assertSee('Product Profitability');
        $this->actingAs($this->branchManager)->get(route('reports.index'))->assertOk()->assertDontSee('Product Profitability');
        $this->actingAs($this->commissaryStaff)->get(route('reports.index'))->assertForbidden(); // decision RQ4
    }

    // ================= R1 Inventory Movement =================

    public function test_inventory_movement_opening_in_out_and_closing(): void
    {
        // Before the period: +20 received, -5 issued  -> opening 15
        $this->record($this->branch, 'received', '20', '2026-10-05 08:00');
        $this->record($this->branch, 'issued', '5', '2026-10-20 08:00');
        // In the period (November): +10 received, -2.5 damaged, -1 count loss
        $this->record($this->branch, 'received', '10', '2026-11-03 08:00');
        $this->record($this->branch, 'damaged', '2.5', '2026-11-04 08:00');
        $this->record($this->branch, 'count_adjustment', '-1', '2026-11-05 08:00');

        $rows = app(\App\Services\ReportService::class)->inventoryMovement(
            $this->branch->id, Carbon::parse('2026-11-01')->startOfDay(), Carbon::parse('2026-11-23')->endOfDay()
        );

        $row = $rows->sole();
        $this->assertSame('15.000', InventoryService::fromUnits(InventoryService::toUnits($row->opening)));
        $this->assertSame('10.000', $row->total_in);
        $this->assertSame('3.500', $row->total_out);
        $this->assertSame('21.500', $row->closing);

        // The closing balance equals the real stock on hand.
        $this->assertSame($this->inventory->quantityOnHand($this->flour, $this->branch), $row->closing);
    }

    public function test_inventory_movement_page_and_csv(): void
    {
        $this->record($this->branch, 'received', '7', now()->toDateTimeString());

        $this->actingAs($this->admin)->get(route('reports.inventory-movement', ['location' => $this->branch->id]))
            ->assertOk()->assertSee('TEST Flour');

        $csv = $this->actingAs($this->admin)
            ->get(route('reports.inventory-movement', ['location' => $this->branch->id, 'export' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('"Item Code",Item,Category,Unit,Opening', $csv);
        $this->assertStringContainsString('TEST-F,"TEST Flour"', $csv);
    }

    public function test_branch_manager_only_sees_own_branch_movement(): void
    {
        $this->record($this->otherBranch, 'received', '7', now()->toDateTimeString());

        // Even when asking for another branch, the report falls back to their own branch (empty here).
        $this->actingAs($this->branchManager)
            ->get(route('reports.inventory-movement', ['location' => $this->otherBranch->id]))
            ->assertOk()->assertDontSee('TEST Flour');
    }

    // ================= R2 Discrepancies =================

    private function makeDiscrepancies(): void
    {
        // Count at branch: system 10, counted 8 -> -2 kg (TEST DATA)
        $this->record($this->branch, 'received', '10', now()->subHour()->toDateTimeString());
        $count = StockCount::create(['location_id' => $this->branch->id, 'counted_by' => $this->branchManager->id,
            'count_date' => today(), 'status' => 'draft']);
        $detail = $count->details()->create(['inventory_item_id' => $this->flour->id, 'counted_quantity' => 8]);
        $outcome = $this->inventory->adjustToCount($this->flour, $this->branch, '8', $this->branchManager, $count->id);
        $detail->update(['system_quantity' => $outcome['system'], 'variance' => $outcome['variance']]);
        $count->update(['status' => 'completed', 'completed_at' => now()]);

        // Transfer commissary -> other branch: sent 5, received 4 -> -1 kg
        $this->record($this->commissary, 'received', '5', now()->subHour()->toDateTimeString());
        $service = app(StockTransferService::class);
        $transfer = $service->create($this->commissaryStaff, $this->commissary, $this->otherBranch, today()->toDateString(),
            [['inventory_item_id' => $this->flour->id, 'quantity' => '5']], null);
        $service->release($transfer, $this->commissaryStaff);
        $service->receive($transfer->fresh('details'), $this->otherBranchManager,
            [$transfer->details()->value('id') => ['quantity_received' => '4', 'remarks' => 'TEST spilled']]);
    }

    public function test_discrepancy_report_combines_counts_and_transfers_with_values_for_management(): void
    {
        $this->makeDiscrepancies();

        $response = $this->actingAs($this->admin)->get(route('reports.discrepancies'));
        $response->assertOk()
            ->assertSee('Stock count')
            ->assertSee('TR-'.now()->year.'-0001')
            ->assertSee('Value Lost')
            ->assertSee('110.00')  // 2 kg x 55.00
            ->assertSee('55.00');  // 1 kg x 55.00
    }

    public function test_branch_manager_sees_own_branch_quantities_without_values(): void
    {
        $this->makeDiscrepancies();

        $this->actingAs($this->branchManager)->get(route('reports.discrepancies'))
            ->assertOk()
            ->assertSee('Stock count')
            ->assertDontSee('TR-'.now()->year.'-0001') // the short transfer belongs to the other branch
            ->assertDontSee('Value Lost')
            ->assertDontSee('110.00');

        $csv = $this->actingAs($this->branchManager)->get(route('reports.discrepancies', ['export' => 'csv']))->streamedContent();
        $this->assertStringNotContainsString('Value', $csv);
    }

    // ================= R3 Production =================

    public function test_production_report_totals(): void
    {
        $cat = InventoryCategory::first();
        $finished = InventoryItem::create(['inventory_category_id' => $cat->id, 'item_code' => 'TEST-P', 'name' => 'TEST Bilao',
            'item_type' => InventoryItem::TYPE_FINISHED_PRODUCT, 'unit_of_measure' => 'bilao']);
        $product = Product::create(['inventory_item_id' => $finished->id, 'pos_product_name' => 'TEST Palabok', 'selling_price' => 300]);
        $this->record($this->commissary, 'received', '50', now()->subHour()->toDateTimeString());

        $production = app(ProductionService::class);
        foreach (['10', '15'] as $qty) {
            $production->record($this->commissaryStaff, $this->commissary, $product, $qty, today(),
                [['inventory_item_id' => $this->flour->id, 'quantity' => '4']]);
        }

        $this->actingAs($this->admin)->get(route('reports.production'))
            ->assertOk()->assertSee('TEST Palabok')->assertSee('25')->assertSee('TEST Flour')->assertSee('8');

        $this->actingAs($this->branchManager)->get(route('reports.production'))->assertOk();
    }

    // ================= R4 Transfers =================

    public function test_transfer_report_per_branch(): void
    {
        $this->makeDiscrepancies();

        $rows = app(\App\Services\ReportService::class)->transfers(
            Location::where('type', 'branch')->pluck('id')->all(), today()->startOfMonth(), now()->endOfDay()
        );

        $other = $rows->firstWhere('location.id', $this->otherBranch->id);
        $this->assertSame(1, $other->total);
        $this->assertSame(1, $other->received);
        $this->assertSame(1, $other->short_lines);
        $this->assertSame(55.0, (float) $other->shortage_value);

        $this->actingAs($this->admin)->get(route('reports.transfers'))->assertOk()->assertSee($this->otherBranch->name);
        $this->actingAs($this->branchManager)->get(route('reports.transfers'))->assertOk()->assertDontSee($this->otherBranch->name);
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->actingAs($this->admin)->get(route('reports.transfers', ['from' => '2026-11-20', 'to' => '2026-11-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_csv_cells_cannot_run_as_excel_formulas(): void
    {
        $this->flour->update(['name' => '=HYPERLINK("http://bad")']);
        $this->record($this->branch, 'received', '1', now()->toDateTimeString());

        $csv = $this->actingAs($this->admin)
            ->get(route('reports.inventory-movement', ['location' => $this->branch->id, 'export' => 'csv']))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
