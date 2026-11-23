<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\ImportedPosSale;
use App\Models\Location;
use App\Services\Analysis\AnalysisException;
use App\Services\Analysis\ProductAnalysisService;
use App\Services\Analysis\SalesForecastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Module 8: Sales Analysis (profitability, frequency, clustering) and Sales Forecast (regression).
 * Viewing: 'view-sales-analysis'. Running: 'run-sales-analysis'. Both Management only (decision AN7).
 */
class SalesAnalysisController extends Controller
{
    public const MAX_PERIOD_DAYS = 366;

    public function index(): View
    {
        $runs = AnalysisRun::with('runBy')
            ->whereIn('analysis_type', [AnalysisRun::TYPE_CLUSTERING, AnalysisRun::TYPE_REGRESSION])
            ->latest('run_at')->latest('id')
            ->paginate(20);

        $range = ImportedPosSale::selectRaw('MIN(sale_datetime) as first_sale, MAX(sale_datetime) as last_sale')->first();

        return view('analysis.index', [
            'runs' => $runs,
            'locations' => Location::orderByRaw("type = 'commissary' desc")->orderBy('name')->get(),
            'firstSale' => $range?->first_sale ? Carbon::parse($range->first_sale) : null,
            'lastSale' => $range?->last_sale ? Carbon::parse($range->last_sale) : null,
        ]);
    }

    public function storeProductAnalysis(Request $request, ProductAnalysisService $service): RedirectResponse
    {
        [$from, $to] = $this->period($request);
        $request->validate(['location_id' => ['nullable', 'integer', 'exists:locations,id']]);

        return $this->runOrFail(fn () => $service->run($request->user(), $from, $to, $request->integer('location_id') ?: null));
    }

    public function storeForecast(Request $request, SalesForecastService $service): RedirectResponse
    {
        [$from, $to] = $this->period($request);

        return $this->runOrFail(fn () => $service->run($request->user(), $from, $to));
    }

    public function show(AnalysisRun $run): View
    {
        abort_unless(in_array($run->analysis_type, [AnalysisRun::TYPE_CLUSTERING, AnalysisRun::TYPE_REGRESSION], true), 404);
        $run->load(['runBy', 'batches.location']);

        if ($run->analysis_type === AnalysisRun::TYPE_CLUSTERING) {
            $results = $run->results()->with('product.inventoryItem')->get()
                ->sortByDesc(fn ($r) => [$r->gross_profit !== null, (float) $r->gross_profit, (float) $r->total_sales])
                ->values();
            $locationId = $run->model_parameters['location_id'] ?? null;

            return view('analysis.products', [
                'run' => $run,
                'results' => $results,
                'location' => $locationId ? Location::find($locationId) : null,
            ]);
        }

        $forecasts = $run->forecasts()->with(['product', 'location'])->orderBy('forecast_date')->get()
            ->groupBy(fn ($f) => "{$f->product_id}-{$f->location_id}")
            ->sortBy(fn ($rows) => [$rows->first()->location->name, $rows->first()->product->pos_product_name]);

        return view('analysis.forecast', [
            'run' => $run,
            'forecasts' => $forecasts,
            'dates' => $run->forecasts()->distinct()->orderBy('forecast_date')->pluck('forecast_date'),
            'history' => $this->history($run),
        ]);
    }

    /** Actual quantity sold per day over the last 8 weeks of the training period, for the forecast chart. */
    private function history(AnalysisRun $run): array
    {
        $start = $run->period_end->copy()->subDays(55)->max($run->period_start);

        $rows = ImportedPosSale::query()
            ->join('sales_import_batches', 'sales_import_batches.id', '=', 'imported_pos_sales.sales_import_batch_id')
            ->whereIn('imported_pos_sales.sales_import_batch_id', $run->batches->pluck('id'))
            ->whereNotNull('imported_pos_sales.product_id')
            ->whereBetween('imported_pos_sales.sale_datetime', [$start->copy()->startOfDay(), $run->period_end->copy()->endOfDay()])
            ->selectRaw('sales_import_batches.location_id, imported_pos_sales.product_id, '
                .'DATE(imported_pos_sales.sale_datetime) as sale_date, SUM(imported_pos_sales.quantity_sold) as quantity')
            ->groupByRaw('sales_import_batches.location_id, imported_pos_sales.product_id, DATE(imported_pos_sales.sale_datetime)')
            ->get();

        $history = ['start' => $start->toDateString(), 'sold' => []];
        foreach ($rows as $row) {
            $history['sold']["{$row->product_id}-{$row->location_id}"][Carbon::parse($row->sale_date)->toDateString()] = (float) $row->quantity;
        }

        return $history;
    }

    private function runOrFail(callable $run): RedirectResponse
    {
        try {
            $result = $run();
        } catch (AnalysisException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('analysis.show', $result)->with('success', 'The analysis finished and was saved.');
    }

    /** Analysis period: required, not in the future, at most MAX_PERIOD_DAYS long. */
    private function period(Request $request): array
    {
        $request->validate([
            'from' => ['required', 'date', 'before_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->startOfDay();

        if ($from->diffInDays($to) + 1 > self::MAX_PERIOD_DAYS) {
            abort(back()->withInput()->withErrors(['from' => 'The period can be at most '.self::MAX_PERIOD_DAYS.' days.']));
        }

        return [$from, $to];
    }
}
