<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\ProductionDetail;
use App\Models\ProductionRecord;
use App\Models\StockCount;
use App\Models\StockCountDetail;
use App\Models\StockTransfer;
use App\Models\StockTransferDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All report calculations (Module 10). Every number is calculated from recorded data:
 * inventory transactions, stock counts, transfers, and production records.
 */
class ReportService
{
    /**
     * R1 Inventory Movement: per item at ONE location over a period.
     *
     * Opening = net of all movements dated BEFORE the period; closing = opening + in - out.
     * Calculated from transaction dates (not entry order), so late entries land in the right period.
     *
     * @return Collection<int, object>
     */
    public function inventoryMovement(int $locationId, Carbon $from, Carbon $to, ?int $categoryId = null, ?string $search = null): Collection
    {
        $inc = $this->sqlList(InventoryTransaction::INCREASE_TYPES);
        $dec = $this->sqlList(InventoryTransaction::DECREASE_TYPES);
        $signed = "CASE WHEN t.transaction_type IN ({$inc}) THEN t.quantity WHEN t.transaction_type IN ({$dec}) THEN -t.quantity ELSE t.quantity END";
        $inPeriod = 't.transaction_date BETWEEN ? AND ?';

        $sum = fn (string $condition) => "COALESCE(SUM(CASE WHEN {$inPeriod} AND {$condition} THEN t.quantity ELSE 0 END), 0)";

        $rows = DB::table('inventory_transactions as t')
            ->join('inventory_items as i', 'i.id', '=', 't.inventory_item_id')
            ->join('inventory_categories as c', 'c.id', '=', 'i.inventory_category_id')
            ->where('t.location_id', $locationId)
            ->where('t.transaction_date', '<=', $to)
            ->when($categoryId, fn ($q) => $q->where('i.inventory_category_id', $categoryId))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('i.name', 'like', "%{$search}%")->orWhere('i.item_code', 'like', "%{$search}%")))
            ->groupBy('i.id', 'i.item_code', 'i.name', 'i.unit_of_measure', 'c.name')
            ->orderBy('i.name')
            ->selectRaw('i.id, i.item_code, i.name, i.unit_of_measure, c.name as category')
            ->selectRaw("COALESCE(SUM(CASE WHEN t.transaction_date < ? THEN {$signed} ELSE 0 END), 0) as opening", [$from])
            ->selectRaw($sum("t.transaction_type = 'received'").' as received', [$from, $to])
            ->selectRaw($sum("t.transaction_type = 'transfer_in'").' as transfer_in', [$from, $to])
            ->selectRaw($sum("t.transaction_type = 'production_output'").' as produced', [$from, $to])
            ->selectRaw($sum("t.transaction_type = 'count_adjustment' AND t.quantity > 0").' as count_gain', [$from, $to])
            ->selectRaw($sum("t.transaction_type = 'issued'").' as issued', [$from, $to])
            ->selectRaw($sum("t.transaction_type = 'damaged'").' as damaged', [$from, $to])
            ->selectRaw($sum("t.transaction_type = 'transfer_out'").' as transfer_out', [$from, $to])
            ->selectRaw($sum("t.transaction_type = 'production_use'").' as used', [$from, $to])
            ->selectRaw("COALESCE(-SUM(CASE WHEN {$inPeriod} AND t.transaction_type = 'count_adjustment' AND t.quantity < 0 THEN t.quantity ELSE 0 END), 0) as count_loss", [$from, $to])
            ->get();

        return $rows->map(function ($row) {
            $u = fn ($v) => InventoryService::toUnits($v);
            $totalIn = $u($row->received) + $u($row->transfer_in) + $u($row->produced) + $u($row->count_gain);
            $totalOut = $u($row->issued) + $u($row->damaged) + $u($row->transfer_out) + $u($row->used) + $u($row->count_loss);

            $row->total_in = InventoryService::fromUnits($totalIn);
            $row->total_out = InventoryService::fromUnits($totalOut);
            $row->closing = InventoryService::fromUnits($u($row->opening) + $totalIn - $totalOut);

            return $row;
        });
    }

    /**
     * R2 Stock Discrepancy: count variances + transfer shortages in the period.
     *
     * @param  array<int, int>  $locationIds
     * @return array{lines: Collection, summary: Collection}
     */
    public function discrepancies(array $locationIds, Carbon $from, Carbon $to): array
    {
        $counts = StockCountDetail::query()
            ->with(['inventoryItem', 'stockCount.location', 'stockCount.countedBy'])
            ->whereNotNull('variance')->where('variance', '!=', 0)
            ->whereHas('stockCount', fn ($q) => $q
                ->where('status', StockCount::STATUS_COMPLETED)
                ->whereIn('location_id', $locationIds)
                ->whereBetween('count_date', [$from->toDateString(), $to->toDateString()]))
            ->get()
            ->map(fn (StockCountDetail $d) => (object) [
                'date' => $d->stockCount->count_date,
                'source' => 'Stock count',
                'reference' => "Count #{$d->stock_count_id}",
                'url' => route('stock-counts.show', $d->stock_count_id),
                'location' => $d->stockCount->location,
                'item' => $d->inventoryItem,
                'expected' => $d->system_quantity,
                'actual' => $d->counted_quantity,
                'difference' => $d->variance,
                'value' => $this->value($d->variance, $d->inventoryItem->unit_cost),
                'by' => $d->stockCount->countedBy->name,
            ]);

        // Transfer shortages belong to the RECEIVING branch's report (that is where the stock went missing).
        $transfers = StockTransferDetail::query()
            ->with(['inventoryItem', 'stockTransfer.destinationLocation', 'stockTransfer.receivedBy'])
            ->whereNotNull('quantity_received')
            ->whereColumn('quantity_received', '!=', 'quantity_sent')
            ->whereHas('stockTransfer', fn ($q) => $q
                ->where('status', StockTransfer::STATUS_RECEIVED)
                ->whereIn('destination_location_id', $locationIds)
                ->whereBetween('received_at', [$from, $to]))
            ->get()
            ->map(function (StockTransferDetail $d) {
                $diff = InventoryService::fromUnits(InventoryService::toUnits($d->quantity_received) - InventoryService::toUnits($d->quantity_sent));

                return (object) [
                    'date' => $d->stockTransfer->received_at,
                    'source' => 'Transfer',
                    'reference' => $d->stockTransfer->reference_no,
                    'url' => route('stock-transfers.show', $d->stock_transfer_id),
                    'location' => $d->stockTransfer->destinationLocation,
                    'item' => $d->inventoryItem,
                    'expected' => $d->quantity_sent,
                    'actual' => $d->quantity_received,
                    'difference' => $diff,
                    'value' => $this->value($diff, $d->inventoryItem->unit_cost),
                    'by' => $d->stockTransfer->receivedBy?->name,
                    'remarks' => $d->remarks,
                ];
            });

        $lines = $counts->concat($transfers)->sortByDesc(fn ($l) => $l->date->timestamp)->values();

        $summary = $lines->groupBy(fn ($l) => $l->location->id)->map(fn (Collection $group) => (object) [
            'location' => $group->first()->location,
            'count_lines' => $group->where('source', 'Stock count')->count(),
            'transfer_lines' => $group->where('source', 'Transfer')->count(),
            'loss_value' => InventoryService::fromUnits(-$group->filter(fn ($l) => (float) $l->value < 0)->sum(fn ($l) => InventoryService::toUnits($l->value))),
            'gain_value' => InventoryService::fromUnits($group->filter(fn ($l) => (float) $l->value > 0)->sum(fn ($l) => InventoryService::toUnits($l->value))),
        ])->sortBy(fn ($s) => $s->location->name)->values();

        return compact('lines', 'summary');
    }

    /**
     * R3 Production: per product, ingredients used, and per day.
     *
     * @return array{products: Collection, ingredients: Collection, daily: Collection}
     */
    public function production(Carbon $from, Carbon $to): array
    {
        // Columns are prefixed with the table name because the queries below JOIN products and
        // inventory_items, which also have a "status" column (MySQL would call it ambiguous).
        $records = ProductionRecord::query()
            ->where('production_records.status', ProductionRecord::STATUS_COMPLETED)
            ->whereBetween('production_records.production_date', [$from->toDateString(), $to->toDateString()]);

        $products = (clone $records)
            ->join('products', 'products.id', '=', 'production_records.product_id')
            ->leftJoin('inventory_items', 'inventory_items.id', '=', 'products.inventory_item_id')
            ->groupBy('products.id', 'products.pos_product_name', 'inventory_items.unit_of_measure')
            ->orderBy('products.pos_product_name')
            ->selectRaw('products.id, products.pos_product_name as name, inventory_items.unit_of_measure as unit,
                         COUNT(*) as batches, SUM(production_records.quantity_produced) as total')
            ->get();

        $ingredients = ProductionDetail::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'production_details.inventory_item_id')
            ->whereIn('production_record_id', (clone $records)->select('production_records.id'))
            ->groupBy('inventory_items.id', 'inventory_items.item_code', 'inventory_items.name', 'inventory_items.unit_of_measure')
            ->orderBy('inventory_items.name')
            ->selectRaw('inventory_items.id, inventory_items.item_code, inventory_items.name, inventory_items.unit_of_measure as unit,
                         SUM(production_details.quantity_used) as total')
            ->get();

        $daily = (clone $records)
            ->join('products', 'products.id', '=', 'production_records.product_id')
            ->groupBy('production_records.production_date', 'products.pos_product_name')
            ->orderByDesc('production_records.production_date')
            ->orderBy('products.pos_product_name')
            ->selectRaw('production_records.production_date as date, products.pos_product_name as name,
                         COUNT(*) as batches, SUM(production_records.quantity_produced) as total')
            ->get();

        return compact('products', 'ingredients', 'daily');
    }

    /**
     * R4 Transfers per destination branch: status counts, shortage lines, average delivery time.
     *
     * @param  array<int, int>  $locationIds
     */
    public function transfers(array $locationIds, Carbon $from, Carbon $to): Collection
    {
        $transfers = StockTransfer::query()
            ->with(['destinationLocation', 'details.inventoryItem'])
            ->whereIn('destination_location_id', $locationIds)
            ->whereBetween('transfer_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return $transfers->groupBy('destination_location_id')->map(function (Collection $group) {
            $received = $group->where('status', StockTransfer::STATUS_RECEIVED);
            $shortLines = $received->flatMap->details
                ->filter(fn ($d) => (float) $d->quantity_received !== (float) $d->quantity_sent);

            $hours = $received->filter(fn ($t) => $t->released_at && $t->received_at)
                ->map(fn ($t) => $t->released_at->diffInMinutes($t->received_at) / 60);

            return (object) [
                'location' => $group->first()->destinationLocation,
                'total' => $group->count(),
                'pending' => $group->where('status', StockTransfer::STATUS_PENDING)->count(),
                'in_transit' => $group->where('status', StockTransfer::STATUS_RELEASED)->count(),
                'received' => $received->count(),
                'cancelled' => $group->where('status', StockTransfer::STATUS_CANCELLED)->count(),
                'lines' => $group->sum(fn ($t) => $t->details->count()),
                'short_lines' => $shortLines->count(),
                'shortage_value' => InventoryService::fromUnits($shortLines->sum(fn ($d) => -InventoryService::toUnits(
                    $this->value(InventoryService::fromUnits(InventoryService::toUnits($d->quantity_received) - InventoryService::toUnits($d->quantity_sent)), $d->inventoryItem->unit_cost)
                ))),
                'avg_hours' => $hours->isEmpty() ? null : round($hours->avg(), 1),
            ];
        })->sortBy(fn ($r) => $r->location->name)->values();
    }

    /** Peso value of a quantity at the item's CURRENT unit cost (2 decimals, exact). */
    private function value(string|float $quantity, string|float $unitCost): string
    {
        // thousandths x centavos = 1/100000 of a peso; round to centavos.
        $amount = InventoryService::toUnits($quantity) * (int) round(((float) $unitCost) * 100);

        return number_format(round($amount / 1000) / 100, 2, '.', '');
    }

    private function sqlList(array $values): string
    {
        return implode(',', array_map(fn ($v) => DB::getPdo()->quote($v), $values));
    }
}
