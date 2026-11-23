<?php

namespace App\Services\Analysis;

use App\Models\AnalysisRun;
use App\Models\ImportedPosSale;
use App\Models\Product;
use App\Models\ProductAnalysisResult;
use App\Models\SalesImportBatch;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Product performance analysis (module 8): profitability + sales frequency + k-means clustering.
 * One run is saved as an AnalysisRun of type "clustering" whose product_analysis_results rows hold
 * all three results per product (decision AN1: a saved snapshot that never changes afterwards).
 *
 *  - AN2 Profit = net sales − quantity × the linked inventory item's unit cost (copied into unit_cost_used).
 *        Margin = profit ÷ net sales. Products without a unit cost are left out of profit ranking and clustering.
 *  - AN3 Sales frequency = number of receipts that contain the product.
 *  - AN4 K-means, 3 groups, on sales frequency and profit margin; named High / Medium / Low performers.
 * Only imported POS rows linked to a product are used (decision PI4). Nothing is estimated or invented.
 */
class ProductAnalysisService
{
    public const CLUSTERS = 3;

    public const CLUSTER_LABELS = ['High performer', 'Medium performer', 'Low performer'];

    public function run(User $user, Carbon $from, Carbon $to, ?int $locationId = null): AnalysisRun
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $batchIds = $this->batchIds($from, $to, $locationId);
        if (! $batchIds) {
            throw new AnalysisException('There are no imported POS sales for this period'.($locationId ? ' and location' : '').'.');
        }

        $sales = $this->salesInPeriod($batchIds, $from, $to);
        $totals = (clone $sales)->whereNotNull('imported_pos_sales.product_id')
            ->selectRaw('imported_pos_sales.product_id, SUM(imported_pos_sales.quantity_sold) as quantity, '
                .'SUM(imported_pos_sales.net_amount) as net, '
                ."COUNT(DISTINCT CONCAT(sales_import_batches.location_id, '|', imported_pos_sales.pos_receipt_no)) as receipts")
            ->groupBy('imported_pos_sales.product_id')
            ->get()
            ->keyBy('product_id');

        if ($totals->isEmpty()) {
            throw new AnalysisException('None of the sales in this period are linked to a product yet. Link them under POS Sales Imports > Unmatched Products.');
        }

        $unmatchedRows = (clone $sales)->whereNull('imported_pos_sales.product_id')->count();
        $products = Product::with('inventoryItem')->findMany($totals->keys());

        $results = $products->map(fn (Product $product) => $this->productResult($product, $totals[$product->id]))->keyBy('product_id');

        // AN4: cluster only products with a known cost (their margin is known).
        $clusterable = $results->filter(fn ($r) => $r['profit_margin'] !== null && $r['total_sales'] > 0);
        $clusterInfo = $this->cluster($clusterable, $results);

        return DB::transaction(function () use ($user, $from, $to, $locationId, $batchIds, $results, $unmatchedRows, $clusterInfo) {
            $run = AnalysisRun::create([
                'run_by' => $user->id,
                'analysis_type' => AnalysisRun::TYPE_CLUSTERING,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'model_parameters' => [
                    'location_id' => $locationId,
                    'method' => 'k-means',
                    'clusters' => self::CLUSTERS,
                    'measures' => ['sales_frequency', 'profit_margin'],
                    'scaling' => 'min-max (0 to 1)',
                    'iterations' => $clusterInfo['iterations'],
                    'products_clustered' => $clusterInfo['count'],
                    'products_without_cost' => $results->whereNull('profit_margin')->count(),
                    'unmatched_rows_skipped' => $unmatchedRows,
                ],
                'run_at' => now(),
                'status' => 'completed',
            ]);
            $run->batches()->attach($batchIds);

            foreach ($results as $result) {
                $run->results()->create($result);
            }

            return $run;
        });
    }

    /** Imports from the chosen location(s) whose sales period overlaps the analysis period. */
    public function batchIds(Carbon $from, Carbon $to, ?int $locationId = null): array
    {
        return SalesImportBatch::where('status', 'completed')
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereDate('period_end', '>=', $from->toDateString())
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->pluck('id')
            ->all();
    }

    private function salesInPeriod(array $batchIds, Carbon $from, Carbon $to)
    {
        return ImportedPosSale::query()
            ->join('sales_import_batches', 'sales_import_batches.id', '=', 'imported_pos_sales.sales_import_batch_id')
            ->whereIn('imported_pos_sales.sales_import_batch_id', $batchIds)
            ->whereBetween('imported_pos_sales.sale_datetime', [$from, $to]);
    }

    /** AN2 + AN3 for one product. Money is calculated in centavos to avoid rounding drift. */
    private function productResult(Product $product, object $total): array
    {
        $salesCents = (int) round((float) $total->net * 100);
        $unitCost = (float) ($product->inventoryItem?->unit_cost ?? 0);

        $result = [
            'product_id' => $product->id,
            'total_quantity_sold' => InventoryService::fromUnits(InventoryService::toUnits($total->quantity)),
            'total_sales' => $salesCents / 100,
            'sales_frequency' => (int) $total->receipts,
            'unit_cost_used' => null,
            'total_cost' => null,
            'gross_profit' => null,
            'profit_margin' => null,
            'cluster_label' => null,
        ];

        if ($unitCost > 0) {
            $costCents = intdiv(InventoryService::toUnits($total->quantity) * (int) round($unitCost * 100) + 500, 1000);
            $result['unit_cost_used'] = $unitCost;
            $result['total_cost'] = $costCents / 100;
            $result['gross_profit'] = ($salesCents - $costCents) / 100;
            $result['profit_margin'] = $salesCents > 0 ? round(($salesCents - $costCents) / $salesCents, 4) : null;
        }

        return $result;
    }

    /** AN4. Fewer products than groups = no clustering (the run says so). */
    private function cluster(Collection $clusterable, Collection $results): array
    {
        if ($clusterable->count() < self::CLUSTERS) {
            return ['iterations' => 0, 'count' => 0];
        }

        $points = $clusterable->map(fn ($r) => [(float) $r['sales_frequency'], (float) $r['profit_margin']])->all();
        $clusters = KMeans::cluster($points, self::CLUSTERS);

        foreach ($clusters['assignments'] as $productId => $group) {
            $result = $results[$productId];
            $result['cluster_label'] = self::CLUSTER_LABELS[$group];
            $results[$productId] = $result;
        }

        return ['iterations' => $clusters['iterations'], 'count' => count($points)];
    }
}
