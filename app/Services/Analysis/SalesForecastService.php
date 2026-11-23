<?php

namespace App\Services\Analysis;

use App\Models\AnalysisRun;
use App\Models\ImportedPosSale;
use App\Models\ProductionForecast;
use App\Models\SalesImportBatch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sales forecasting with regression (module 8). Saved as an AnalysisRun of type "regression".
 *
 *  - AN5 For every product at every location, a multiple linear regression is fitted on the
 *        quantity sold per day:   quantity = b0 + b1·day_number + (an effect for each day of the week)
 *        Monday is the base day; the other six days each get their own effect, because
 *        weekends usually sell differently. Needs MIN_DAYS days with at least 2 of every weekday;
 *        with less data the forecast is the plain daily average (marked "average").
 *  - AN6 Forecast = the HORIZON days right after the end of the training period.
 *
 * Only days covered by an import of that location count. A covered day without a sale of the
 * product counts as 0 sold. Days with no import at all are left out (no data, not "zero").
 * Only products linked to a product record and still active are forecast.
 */
class SalesForecastService
{
    public const HORIZON = 7;

    public const MIN_DAYS = 28;

    public const WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    public function run(User $user, Carbon $from, Carbon $to): AnalysisRun
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $batches = SalesImportBatch::where('status', 'completed')
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereDate('period_end', '>=', $from->toDateString())
            ->get(['id', 'location_id', 'period_start', 'period_end']);

        if ($batches->isEmpty()) {
            throw new AnalysisException('There are no imported POS sales for this period.');
        }

        $coveredDays = $this->coveredDays($batches, $from, $to);
        $daily = $this->dailySales($batches->pluck('id')->all(), $from, $to);

        if (! $daily) {
            throw new AnalysisException('None of the sales in this period belong to an active product linked in the system.');
        }

        $models = [];
        $forecasts = [];
        $forecastDates = array_map(fn ($i) => $to->copy()->addDays($i), range(1, self::HORIZON));

        foreach ($daily as $locationId => $products) {
            $days = $coveredDays[$locationId] ?? [];

            foreach ($products as $productId => $soldByDate) {
                $series = [];
                foreach ($days as $date) {
                    $series[$date] = $soldByDate[$date] ?? 0.0;
                }

                $model = $this->fit($series, $from);
                $models["{$productId}-{$locationId}"] = $model['summary'];

                foreach ($forecastDates as $date) {
                    $forecasts[] = [
                        'product_id' => $productId,
                        'location_id' => $locationId,
                        'forecast_date' => $date->toDateString(),
                        'predicted_quantity' => round(max(0.0, $model['predict']($date)), 3),
                    ];
                }
            }
        }

        $r2Values = array_filter(array_column($models, 'r2'), fn ($r2) => $r2 !== null);

        return DB::transaction(function () use ($user, $from, $to, $batches, $models, $forecasts, $r2Values) {
            $run = AnalysisRun::create([
                'run_by' => $user->id,
                'analysis_type' => AnalysisRun::TYPE_REGRESSION,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'model_parameters' => [
                    'method' => 'multiple linear regression: trend + day of week',
                    'horizon_days' => self::HORIZON,
                    'minimum_days' => self::MIN_DAYS,
                    'models' => $models,
                ],
                // Average R² of the regression models (models using the plain average have no R²).
                'accuracy_score' => $r2Values ? round(array_sum($r2Values) / count($r2Values), 4) : null,
                'run_at' => now(),
                'status' => 'completed',
            ]);
            $run->batches()->attach($batches->pluck('id')->all());

            $timestamp = now();
            foreach (array_chunk($forecasts, 500) as $chunk) {
                ProductionForecast::insert(array_map(fn ($f) => $f + [
                    'analysis_run_id' => $run->id, 'created_at' => $timestamp, 'updated_at' => $timestamp,
                ], $chunk));
            }

            return $run;
        });
    }

    /**
     * Fit one product at one location.
     *
     * @param  array<string, float>  $series  'Y-m-d' => quantity sold (covered days only)
     * @return array{summary: array, predict: \Closure(Carbon): float}
     */
    public function fit(array $series, Carbon $periodStart): array
    {
        $days = count($series);
        $weekdayCounts = array_count_values(array_map(fn ($d) => Carbon::parse($d)->dayOfWeekIso, array_keys($series)));
        $enoughData = $days >= self::MIN_DAYS && count($weekdayCounts) === 7 && min($weekdayCounts) >= 2;

        if ($enoughData) {
            $x = [];
            foreach (array_keys($series) as $date) {
                $x[] = $this->predictors(Carbon::parse($date), $periodStart);
            }

            if ($fit = LinearRegression::fit($x, array_values($series))) {
                $c = $fit['coefficients'];
                $weekdayEffects = ['Mon' => 0.0];
                foreach (range(2, 7) as $i => $iso) {
                    $weekdayEffects[self::WEEKDAYS[$iso]] = round($c[$i + 2], 3);
                }

                return [
                    'summary' => [
                        'method' => 'regression',
                        'days' => $days,
                        'r2' => $fit['r2'] !== null ? round($fit['r2'], 4) : null,
                        'base' => round($c[0], 3),
                        'trend_per_day' => round($c[1], 4),
                        'weekday_effects' => $weekdayEffects,
                    ],
                    'predict' => fn (Carbon $date) => LinearRegression::predict($c, $this->predictors($date, $periodStart)),
                ];
            }
        }

        $average = $days ? array_sum($series) / $days : 0.0;

        return [
            'summary' => ['method' => 'average', 'days' => $days, 'r2' => null, 'daily_average' => round($average, 3)],
            'predict' => fn (Carbon $date) => $average,
        ];
    }

    /** [day number since the period start, Tue?, Wed?, Thu?, Fri?, Sat?, Sun?]  (Monday = all zeros) */
    private function predictors(Carbon $date, Carbon $periodStart): array
    {
        $row = [(float) $periodStart->diffInDays($date)];
        foreach (range(2, 7) as $iso) {
            $row[] = $date->dayOfWeekIso === $iso ? 1.0 : 0.0;
        }

        return $row;
    }

    /** location_id => sorted list of 'Y-m-d' days that some import of that location covers. */
    private function coveredDays($batches, Carbon $from, Carbon $to): array
    {
        $covered = [];
        foreach ($batches as $batch) {
            $start = $batch->period_start->max($from)->copy();
            $end = $batch->period_end->min($to);
            for ($day = $start; $day->lte($end); $day->addDay()) {
                $covered[$batch->location_id][$day->toDateString()] = true;
            }
        }

        return array_map(function ($days) {
            $days = array_keys($days);
            sort($days);

            return $days;
        }, $covered);
    }

    /** location_id => product_id => 'Y-m-d' => quantity sold */
    private function dailySales(array $batchIds, Carbon $from, Carbon $to): array
    {
        $rows = ImportedPosSale::query()
            ->join('sales_import_batches', 'sales_import_batches.id', '=', 'imported_pos_sales.sales_import_batch_id')
            ->join('products', 'products.id', '=', 'imported_pos_sales.product_id')
            ->whereIn('imported_pos_sales.sales_import_batch_id', $batchIds)
            ->where('products.status', 'active')
            ->whereBetween('imported_pos_sales.sale_datetime', [$from, $to->copy()->endOfDay()])
            ->selectRaw('sales_import_batches.location_id, imported_pos_sales.product_id, '
                .'DATE(imported_pos_sales.sale_datetime) as sale_date, SUM(imported_pos_sales.quantity_sold) as quantity')
            ->groupBy('sales_import_batches.location_id', 'imported_pos_sales.product_id', DB::raw('DATE(imported_pos_sales.sale_datetime)'))
            ->get();

        $daily = [];
        foreach ($rows as $row) {
            $daily[$row->location_id][$row->product_id][Carbon::parse($row->sale_date)->toDateString()] = (float) $row->quantity;
        }

        return $daily;
    }
}
