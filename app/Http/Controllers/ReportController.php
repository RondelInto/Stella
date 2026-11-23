<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\InventoryCategory;
use App\Models\Location;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Centralized reports (Module 10).
 *  - Management ('view-all-reports'): every location, with peso values (team decision RQ3).
 *  - Branch Managers ('view-branch-reports'): their own branch, quantities only.
 * Every report can be printed (browser Save as PDF) or downloaded as CSV (team decision RQ2).
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function index(): View
    {
        return view('reports.index');
    }

    /** R1 Inventory Movement */
    public function inventoryMovement(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $locations = $this->locations($request);
        $location = $locations->firstWhere('id', (int) $request->input('location')) ?? $locations->first();
        abort_unless($location, 403, 'Your account is not assigned to a location.');

        $categoryId = $request->integer('category') ?: null;
        $rows = $this->reports->inventoryMovement($location->id, $from, $to, $categoryId, $request->input('search'));

        if ($request->input('export') === 'csv') {
            return $this->csv("inventory-movement_{$location->name}_{$from->toDateString()}_{$to->toDateString()}.csv",
                ['Item Code', 'Item', 'Category', 'Unit', 'Opening', 'Received', 'Transfer In', 'Produced', 'Count Gain', 'Total In',
                    'Issued', 'Damaged', 'Transfer Out', 'Used in Production', 'Count Loss', 'Total Out', 'Closing'],
                $rows->map(fn ($r) => [$r->item_code, $r->name, $r->category, $r->unit_of_measure, $r->opening, $r->received,
                    $r->transfer_in, $r->produced, $r->count_gain, $r->total_in, $r->issued, $r->damaged, $r->transfer_out,
                    $r->used, $r->count_loss, $r->total_out, $r->closing]));
        }

        return view('reports.inventory-movement', [
            'rows' => $rows,
            'from' => $from, 'to' => $to,
            'locations' => $locations, 'location' => $location,
            'categories' => InventoryCategory::orderBy('name')->get(),
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    /** R2 Stock Discrepancy */
    public function discrepancies(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $locations = $this->locations($request);
        $locationIds = $request->filled('location') && $locations->contains('id', (int) $request->input('location'))
            ? [(int) $request->input('location')]
            : $locations->pluck('id')->all();

        $result = $this->reports->discrepancies($locationIds, $from, $to);
        $showValues = $request->user()->can('view-all-reports');

        if ($request->input('export') === 'csv') {
            $headers = ['Date', 'Source', 'Reference', 'Location', 'Item Code', 'Item', 'Unit', 'Expected', 'Actual', 'Difference'];
            if ($showValues) {
                $headers[] = 'Value (PHP)';
            }

            return $this->csv("stock-discrepancies_{$from->toDateString()}_{$to->toDateString()}.csv", $headers,
                $result['lines']->map(fn ($l) => array_merge(
                    [$l->date->format('Y-m-d'), $l->source, $l->reference, $l->location->name, $l->item->item_code,
                        $l->item->name, $l->item->unit_of_measure, $l->expected, $l->actual, $l->difference],
                    $showValues ? [$l->value] : []
                )));
        }

        return view('reports.discrepancies', $result + [
            'from' => $from, 'to' => $to,
            'locations' => $locations,
            'selectedLocation' => count($locationIds) === 1 ? $locationIds[0] : null,
            'showValues' => $showValues,
        ]);
    }

    /** R3 Production (production happens at the commissary; viewing needs 'view-production' too). */
    public function production(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()->can('view-production'), 403);
        [$from, $to] = $this->period($request);

        $result = $this->reports->production($from, $to);

        if ($request->input('export') === 'csv') {
            return $this->csv("production_{$from->toDateString()}_{$to->toDateString()}.csv",
                ['Date', 'Product', 'Batches', 'Quantity Made'],
                $result['daily']->map(fn ($d) => [Carbon::parse($d->date)->toDateString(), $d->name, $d->batches, $d->total]));
        }

        return view('reports.production', $result + ['from' => $from, 'to' => $to]);
    }

    /** R4 Transfers per branch */
    public function transfers(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $branchIds = $this->locations($request)->where('type', Location::TYPE_BRANCH)->pluck('id')->all();

        $rows = $this->reports->transfers($branchIds, $from, $to);
        $showValues = $request->user()->can('view-all-reports');

        if ($request->input('export') === 'csv') {
            $headers = ['Branch', 'Transfers', 'Pending', 'In Transit', 'Received', 'Cancelled', 'Item Lines', 'Short Lines', 'Avg Hours Release to Receipt'];
            if ($showValues) {
                $headers[] = 'Shortage Value (PHP)';
            }

            return $this->csv("transfers_{$from->toDateString()}_{$to->toDateString()}.csv", $headers,
                $rows->map(fn ($r) => array_merge(
                    [$r->location->name, $r->total, $r->pending, $r->in_transit, $r->received, $r->cancelled, $r->lines, $r->short_lines, $r->avg_hours],
                    $showValues ? [$r->shortage_value] : []
                )));
        }

        return view('reports.transfers', ['rows' => $rows, 'from' => $from, 'to' => $to, 'showValues' => $showValues]);
    }

    /** R6 Product Profitability, from a saved product analysis run (Management only). */
    public function profitability(Request $request): View|StreamedResponse
    {
        [$run, $runs] = $this->analysisRun($request);
        $results = $run?->results()->with('product')->get()
            ->sortByDesc(fn ($r) => [$r->gross_profit !== null, (float) $r->gross_profit, (float) $r->total_sales])->values()
            ?? collect();

        if ($run && $request->input('export') === 'csv') {
            return $this->csv("profitability_run-{$run->id}_{$run->period_start->toDateString()}_{$run->period_end->toDateString()}.csv",
                ['Product', 'Group', 'Quantity Sold', 'Receipts', 'Net Sales (PHP)', 'Unit Cost (PHP)', 'Total Cost (PHP)', 'Gross Profit (PHP)', 'Margin (%)'],
                $results->map(fn ($r) => [$r->product->pos_product_name, $r->cluster_label ?? '', $r->total_quantity_sold, $r->sales_frequency,
                    $r->total_sales, $r->unit_cost_used ?? 'not set', $r->total_cost ?? '', $r->gross_profit ?? '',
                    $r->profit_margin !== null ? round((float) $r->profit_margin * 100, 2) : '']));
        }

        return view('reports.profitability', compact('run', 'runs', 'results'));
    }

    /** R7 Sales Frequency, from a saved product analysis run (Management only). */
    public function salesFrequency(Request $request): View|StreamedResponse
    {
        [$run, $runs] = $this->analysisRun($request);
        $days = $run ? $run->period_start->diffInDays($run->period_end) + 1 : 1;
        $results = $run?->results()->with('product')->get()
            ->sortByDesc(fn ($r) => [$r->sales_frequency, (float) $r->total_quantity_sold])->values()
            ->map(function ($r) use ($days) {
                $r->receipts_per_day = round($r->sales_frequency / $days, 2);
                $r->qty_per_receipt = $r->sales_frequency ? round((float) $r->total_quantity_sold / $r->sales_frequency, 2) : 0;

                return $r;
            }) ?? collect();

        if ($run && $request->input('export') === 'csv') {
            return $this->csv("sales-frequency_run-{$run->id}_{$run->period_start->toDateString()}_{$run->period_end->toDateString()}.csv",
                ['Rank', 'Product', 'Receipts', 'Receipts per Day', 'Quantity Sold', 'Quantity per Receipt', 'Net Sales (PHP)', 'Group'],
                $results->values()->map(fn ($r, $i) => [$i + 1, $r->product->pos_product_name, $r->sales_frequency, $r->receipts_per_day,
                    $r->total_quantity_sold, $r->qty_per_receipt, $r->total_sales, $r->cluster_label ?? '']));
        }

        return view('reports.sales-frequency', compact('run', 'runs', 'results', 'days'));
    }

    // ---------------------------------------------------------------

    /** The chosen (?run=) or latest product analysis run, plus the list to choose from. */
    private function analysisRun(Request $request): array
    {
        $runs = AnalysisRun::where('analysis_type', AnalysisRun::TYPE_CLUSTERING)
            ->latest('run_at')->latest('id')->limit(50)->get();
        $run = $request->filled('run')
            ? $runs->firstWhere('id', $request->integer('run')) ?? AnalysisRun::where('analysis_type', AnalysisRun::TYPE_CLUSTERING)->findOrFail($request->integer('run'))
            : $runs->first();

        return [$run, $runs];
    }

    /** Date range from ?from=&to= (default: this month so far). Never in the future. */
    private function period(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->input('from', today()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', today()->toDateString()))->endOfDay();

        return [$from, $to->isFuture() ? now()->endOfDay() : $to];
    }

    /** Locations this user may report on: all for Management, own branch for Branch Managers. */
    private function locations(Request $request)
    {
        return Location::whereIn('id', $request->user()->accessibleLocationIds())
            ->orderByRaw("type = 'commissary' desc")
            ->orderBy('name')
            ->get();
    }

    /**
     * Download rows as CSV (opens in Excel). A UTF-8 BOM is added so "ñ" shows correctly.
     */
    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                // Prevent "CSV injection": a cell starting with = + - @ could run as a formula in Excel.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) && ! is_numeric($v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
