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
use App\Services\Analysis\SalesForecastService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Module 8: sales analysis and forecasting (decisions AN1–AN8). All products, prices and sales are TEST DATA.
 */
class SalesAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Location $branch;
    private Location $otherBranch;
    private Location $commissary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->first();
        [$this->branch, $this->otherBranch] = Location::where('type', Location::TYPE_BRANCH)->orderBy('id')->take(2)->get();
        $this->commissary = Location::where('type', Location::TYPE_COMMISSARY)->first();
    }

    private function employee(string $roleSlug, Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->syncRoleAssignments([Role::where('slug', $roleSlug)->value('id')]);

        return $user->fresh();
    }

    private function product(string $name, string $unitCost): Product
    {
        $item = InventoryItem::create([
            'inventory_category_id' => InventoryCategory::first()->id, 'item_code' => 'TEST-'.uniqid(), 'name' => "TEST {$name}",
            'item_type' => InventoryItem::TYPE_FINISHED_PRODUCT, 'unit_of_measure' => 'pc', 'unit_cost' => $unitCost,
        ]);

        return Product::create(['inventory_item_id' => $item->id, 'pos_product_name' => $name, 'selling_price' => 100]);
    }

    private function batch(Location $location, string $start, string $end): SalesImportBatch
    {
        return SalesImportBatch::create(['location_id' => $location->id, 'imported_by' => $this->admin->id, 'file_name' => 'test.csv',
            'period_start' => $start, 'period_end' => $end, 'total_records' => 0, 'status' => 'completed']);
    }

    private function sale(SalesImportBatch $batch, ?Product $product, string $receipt, string $at, string $qty, string $net): void
    {
        ImportedPosSale::create(['sales_import_batch_id' => $batch->id, 'product_id' => $product?->id,
            'pos_product_name' => $product?->pos_product_name ?? 'TEST Unknown', 'pos_receipt_no' => $receipt,
            'sale_datetime' => $at, 'quantity_sold' => $qty, 'unit_price' => 0, 'net_amount' => $net]);
    }

    // ================= Access =================

    public function test_only_management_can_view_and_run_analysis(): void
    {
        $this->actingAs($this->admin)->get(route('analysis.index'))->assertOk()->assertSee('Run Product Analysis');

        foreach ([Role::BRANCH_MANAGER, Role::COMMISSARY_STAFF, Role::BRANCH_STAFF] as $slug) {
            $user = $this->employee($slug, $this->branch);
            $this->actingAs($user)->get(route('analysis.index'))->assertForbidden();
            $this->actingAs($user)->post(route('analysis.products.store'), ['from' => '2026-11-01', 'to' => '2026-11-02'])->assertForbidden();
            $this->actingAs($user)->post(route('analysis.forecast.store'), ['from' => '2026-11-01', 'to' => '2026-11-02'])->assertForbidden();
        }

        $manager = $this->employee(Role::BRANCH_MANAGER, $this->branch);
        $this->actingAs($manager)->get(route('reports.profitability'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.sales-frequency'))->assertForbidden();
    }

    // ================= Product analysis (AN1–AN4) =================

    public function test_profit_frequency_and_snapshot(): void
    {
        $palabok = $this->product('TEST Palabok', '200.00');
        $kakanin = $this->product('TEST Kakanin', '0.00'); // no cost set

        $b1 = $this->batch($this->branch, '2026-11-01', '2026-11-02');
        $this->sale($b1, $palabok, 'R1', '2026-11-01 10:00', '2', '600.00');
        $this->sale($b1, $palabok, 'R1', '2026-11-01 10:00', '1', '300.00'); // same receipt: frequency counts it once
        $this->sale($b1, $palabok, 'R2', '2026-11-02 11:00', '1', '300.00');
        $this->sale($b1, $kakanin, 'R2', '2026-11-02 11:00', '3', '150.00');
        $this->sale($b1, null, 'R3', '2026-11-02 12:00', '1', '99.00');     // unmatched: skipped

        $b2 = $this->batch($this->otherBranch, '2026-11-01', '2026-11-01');
        $this->sale($b2, $palabok, 'R1', '2026-11-01 09:00', '1', '300.00'); // same receipt no. at another branch = another receipt

        $this->actingAs($this->admin)->post(route('analysis.products.store'), ['from' => '2026-11-01', 'to' => '2026-11-02'])
            ->assertSessionHas('success');

        $run = AnalysisRun::sole();
        $this->assertSame(AnalysisRun::TYPE_CLUSTERING, $run->analysis_type);
        $this->assertSame(1, $run->model_parameters['unmatched_rows_skipped']);
        $this->assertSame(1, $run->model_parameters['products_without_cost']);
        $this->assertEqualsCanonicalizing([$b1->id, $b2->id], $run->batches->pluck('id')->all());

        $p = $run->results()->where('product_id', $palabok->id)->sole();
        $this->assertSame('5.000', $p->total_quantity_sold);
        $this->assertSame('1500.00', $p->total_sales);
        $this->assertSame(3, $p->sales_frequency);        // R1@branch, R2@branch, R1@other branch
        $this->assertSame('200.00', $p->unit_cost_used);
        $this->assertSame('1000.00', $p->total_cost);
        $this->assertSame('500.00', $p->gross_profit);
        $this->assertSame('0.3333', $p->profit_margin);

        $k = $run->results()->where('product_id', $kakanin->id)->sole();
        $this->assertNull($k->gross_profit);
        $this->assertNull($k->cluster_label); // only 1 product with cost: no grouping

        // AN1: a later cost change does not change the saved run.
        $palabok->inventoryItem->update(['unit_cost' => '250.00']);
        $this->assertSame('500.00', $p->fresh()->gross_profit);

        // PI7: imports used by a run can no longer be deleted.
        $this->assertFalse($b1->canBeDeleted());

        $this->actingAs($this->admin)->get(route('analysis.show', $run))
            ->assertOk()->assertSee('TEST Palabok')->assertSee('Cost not set')->assertSee('33.3%');
    }

    public function test_location_filter_uses_only_that_locations_imports(): void
    {
        $palabok = $this->product('TEST Palabok', '200.00');
        $b1 = $this->batch($this->branch, '2026-11-01', '2026-11-01');
        $this->sale($b1, $palabok, 'R1', '2026-11-01 10:00', '1', '300.00');
        $b2 = $this->batch($this->otherBranch, '2026-11-01', '2026-11-01');
        $this->sale($b2, $palabok, 'R1', '2026-11-01 10:00', '4', '1200.00');

        $this->actingAs($this->admin)->post(route('analysis.products.store'),
            ['from' => '2026-11-01', 'to' => '2026-11-01', 'location_id' => $this->otherBranch->id]);

        $run = AnalysisRun::sole();
        $this->assertSame([$b2->id], $run->batches->pluck('id')->all());
        $this->assertSame('1200.00', $run->results()->sole()->total_sales);
    }

    public function test_products_are_grouped_into_high_medium_low(): void
    {
        $batch = $this->batch($this->branch, '2026-11-01', '2026-11-01');
        // [name, cost, receipts, price]: margin = 1 − cost/price
        $data = [['Star A', '40', 30, 100], ['Star B', '42', 28, 100], ['Mid A', '65', 15, 100],
            ['Mid B', '67', 14, 100], ['Low A', '90', 2, 100], ['Low B', '92', 3, 100]];
        $products = [];
        foreach ($data as [$name, $cost, $receipts, $price]) {
            $products[$name] = $this->product("TEST {$name}", $cost);
            for ($i = 1; $i <= $receipts; $i++) {
                $this->sale($batch, $products[$name], "{$name}-{$i}", '2026-11-01 10:00', '1', (string) $price);
            }
        }

        $this->actingAs($this->admin)->post(route('analysis.products.store'), ['from' => '2026-11-01', 'to' => '2026-11-01']);

        $labels = AnalysisRun::sole()->results()->with('product')->get()->pluck('cluster_label', 'product.pos_product_name');
        $this->assertSame('High performer', $labels['TEST Star A']);
        $this->assertSame('High performer', $labels['TEST Star B']);
        $this->assertSame('Medium performer', $labels['TEST Mid A']);
        $this->assertSame('Medium performer', $labels['TEST Mid B']);
        $this->assertSame('Low performer', $labels['TEST Low A']);
        $this->assertSame('Low performer', $labels['TEST Low B']);
    }

    public function test_analysis_without_sales_shows_a_clear_message(): void
    {
        $this->actingAs($this->admin)->post(route('analysis.products.store'), ['from' => '2026-11-01', 'to' => '2026-11-02'])
            ->assertSessionHas('error', 'There are no imported POS sales for this period.');

        $this->actingAs($this->admin)->post(route('analysis.forecast.store'), ['from' => '2026-11-01', 'to' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('to');

        $this->assertSame(0, AnalysisRun::count());
    }

    // ================= Forecast (AN5–AN6) =================

    /** Sales that follow  quantity = 10 + 0.5 × day number, +6 on Saturdays  exactly (TEST DATA). */
    private function seedPatternedSales(Product $product, Location $location, Carbon $from, int $days): SalesImportBatch
    {
        $batch = $this->batch($location, $from->toDateString(), $from->copy()->addDays($days - 1)->toDateString());
        for ($t = 0; $t < $days; $t++) {
            $date = $from->copy()->addDays($t);
            $qty = 10 + 0.5 * $t + ($date->isSaturday() ? 6 : 0);
            $this->sale($batch, $product, "R{$t}", $date->toDateString().' 12:00', (string) $qty, (string) ($qty * 10));
        }

        return $batch;
    }

    public function test_regression_learns_trend_and_weekday_and_forecasts_seven_days(): void
    {
        $palabok = $this->product('TEST Palabok', '200.00');
        $from = Carbon::parse('2026-09-07'); // a Monday
        $this->seedPatternedSales($palabok, $this->branch, $from, 35);

        $this->actingAs($this->admin)->post(route('analysis.forecast.store'), ['from' => '2026-09-07', 'to' => '2026-10-11'])
            ->assertSessionHas('success');

        $run = AnalysisRun::sole();
        $model = $run->model_parameters['models']["{$palabok->id}-{$this->branch->id}"];
        $this->assertSame('regression', $model['method']);
        $this->assertSame(35, $model['days']);
        $this->assertEqualsWithDelta(1.0, $model['r2'], 1e-6);
        $this->assertEqualsWithDelta(0.5, $model['trend_per_day'], 1e-4);
        $this->assertEqualsWithDelta(6.0, $model['weekday_effects']['Sat'], 1e-3);
        $this->assertEqualsWithDelta(0.0, $model['weekday_effects']['Wed'], 1e-3);

        $forecasts = $run->forecasts()->orderBy('forecast_date')->get();
        $this->assertCount(SalesForecastService::HORIZON, $forecasts);
        $this->assertSame('2026-10-12', $forecasts->first()->forecast_date->toDateString());
        foreach ($forecasts as $f) {
            $t = $from->diffInDays($f->forecast_date);
            $expected = 10 + 0.5 * $t + ($f->forecast_date->isSaturday() ? 6 : 0);
            $this->assertEqualsWithDelta($expected, (float) $f->predicted_quantity, 0.01);
        }

        $this->actingAs($this->admin)->get(route('analysis.show', $run))->assertOk()->assertSee('Regression')->assertSee('TEST Palabok');
    }

    public function test_short_history_falls_back_to_the_daily_average(): void
    {
        $palabok = $this->product('TEST Palabok', '200.00');
        $this->seedPatternedSales($palabok, $this->branch, Carbon::parse('2026-09-07'), 10); // 10 + ... + 14.5, one Saturday

        $this->actingAs($this->admin)->post(route('analysis.forecast.store'), ['from' => '2026-09-07', 'to' => '2026-09-16']);

        $model = AnalysisRun::sole()->model_parameters['models']["{$palabok->id}-{$this->branch->id}"];
        $this->assertSame('average', $model['method']);
        $this->assertNull($model['r2']);
        $this->assertEqualsWithDelta((array_sum(range(10, 14.5, 0.5)) + 6) / 10, $model['daily_average'], 0.001);
        $this->assertNull(AnalysisRun::sole()->accuracy_score);
    }

    public function test_days_without_an_import_are_not_counted_as_zero_sales(): void
    {
        $palabok = $this->product('TEST Palabok', '200.00');
        // Two imports with a gap (Sep 4–6 not imported); a covered day without sales DOES count as 0.
        $b1 = $this->batch($this->branch, '2026-09-01', '2026-09-03');
        $this->sale($b1, $palabok, 'A', '2026-09-01 12:00', '6', '60');
        $this->sale($b1, $palabok, 'B', '2026-09-03 12:00', '6', '60'); // Sep 2 covered, 0 sold
        $b2 = $this->batch($this->branch, '2026-09-07', '2026-09-07');
        $this->sale($b2, $palabok, 'C', '2026-09-07 12:00', '12', '120');

        $this->actingAs($this->admin)->post(route('analysis.forecast.store'), ['from' => '2026-09-01', 'to' => '2026-09-07']);

        $model = AnalysisRun::sole()->model_parameters['models']["{$palabok->id}-{$this->branch->id}"];
        $this->assertSame(4, $model['days']);                 // Sep 1, 2, 3, 7
        $this->assertEqualsWithDelta(6.0, $model['daily_average'], 0.001); // (6 + 0 + 6 + 12) / 4
    }

    // ================= Planning page (AN7) =================

    public function test_planning_shows_expected_sales_without_pesos_per_visible_location(): void
    {
        $palabok = $this->product('TEST Palabok', '200.00');
        $from = today()->subDays(35);
        $this->seedPatternedSales($palabok, $this->branch, $from, 35);
        $this->seedPatternedSales($palabok, $this->otherBranch, $from, 35);

        $this->actingAs($this->admin)->post(route('analysis.forecast.store'),
            ['from' => $from->toDateString(), 'to' => today()->subDay()->toDateString()]);
        $run = AnalysisRun::sole();

        $branchTotal = (float) $run->forecasts()->where('location_id', $this->branch->id)->sum('predicted_quantity');
        $allTotal = (float) $run->forecasts()->sum('predicted_quantity');

        $commissaryStaff = $this->employee(Role::COMMISSARY_STAFF, $this->commissary);
        $this->actingAs($commissaryStaff)->get(route('production.planning'))
            ->assertOk()->assertSee('Expected Sales')->assertSee(rtrim(rtrim(number_format($allTotal, 3, '.', ','), '0'), '.'))
            ->assertDontSee('₱');

        $manager = $this->employee(Role::BRANCH_MANAGER, $this->branch);
        $this->actingAs($manager)->get(route('production.planning'))
            ->assertOk()->assertSee(rtrim(rtrim(number_format($branchTotal, 3, '.', ','), '0'), '.'));
    }

    // ================= SAMPLE data for manual testing =================

    public function test_sample_sales_seeder_supports_both_analyses(): void
    {
        $this->seed(\Database\Seeders\SampleSalesDataSeeder::class);
        $this->seed(\Database\Seeders\SampleSalesDataSeeder::class); // second run adds nothing

        $this->assertSame(2, SalesImportBatch::where('file_name', 'SAMPLE generated test data')->count());
        $this->assertSame(6, Product::where('pos_product_name', 'like', 'SAMPLE %')->count());

        $period = ['from' => today()->subDays(60)->toDateString(), 'to' => today()->subDay()->toDateString()];
        $this->actingAs($this->admin)->post(route('analysis.products.store'), $period)->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('analysis.forecast.store'), $period)->assertSessionHas('success');

        $products = AnalysisRun::where('analysis_type', AnalysisRun::TYPE_CLUSTERING)->sole();
        $this->assertSame(6, $products->results()->whereNotNull('cluster_label')->count());

        $forecast = AnalysisRun::where('analysis_type', AnalysisRun::TYPE_REGRESSION)->sole();
        $this->assertSame(12 * SalesForecastService::HORIZON, $forecast->forecasts()->count()); // 6 products x 2 branches
        $this->assertSame(['regression'], collect($forecast->model_parameters['models'])->pluck('method')->unique()->values()->all());
        $this->assertNotNull($forecast->accuracy_score);

        $this->actingAs($this->admin)->get(route('analysis.show', $products))->assertOk()->assertSee('High performer');
        $this->actingAs($this->admin)->get(route('analysis.show', $forecast))->assertOk();
        $this->actingAs($this->admin)->get(route('production.planning'))->assertOk()->assertSee('Expected Sales');
    }

    // ================= Reports R6–R7 =================

    public function test_profitability_and_frequency_reports_with_csv(): void
    {
        $this->actingAs($this->admin)->get(route('reports.profitability'))->assertOk()->assertSee('Run one first');

        $palabok = $this->product('TEST Palabok', '200.00');
        $batch = $this->batch($this->branch, '2026-11-01', '2026-11-02');
        $this->sale($batch, $palabok, 'R1', '2026-11-01 10:00', '2', '600.00');
        $this->sale($batch, $palabok, 'R2', '2026-11-02 10:00', '2', '600.00');
        $this->actingAs($this->admin)->post(route('analysis.products.store'), ['from' => '2026-11-01', 'to' => '2026-11-02']);

        $this->actingAs($this->admin)->get(route('reports.index'))->assertSee(route('reports.profitability'));
        $this->actingAs($this->admin)->get(route('reports.profitability'))->assertOk()->assertSee('TEST Palabok')->assertSee('400.00');
        $this->actingAs($this->admin)->get(route('reports.sales-frequency'))->assertOk()->assertSee('TEST Palabok')->assertSee('1.00');

        $csv = $this->actingAs($this->admin)->get(route('reports.profitability', ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('"TEST Palabok",,4.000,2,1200.00,200.00,800.00,400.00,33.33', $csv);

        $csv = $this->actingAs($this->admin)->get(route('reports.sales-frequency', ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('1,"TEST Palabok",2,1,4.000,2,1200.00', $csv);
    }
}
