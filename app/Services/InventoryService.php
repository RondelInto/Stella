<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * THE ONLY PLACE where stock quantities change.
 *
 * Every stock movement (manual transactions, transfers, production, stock counts) must call
 * InventoryService::record(). It guarantees that:
 *   1. the stock row is LOCKED while it is updated (two users saving at once can't corrupt totals),
 *   2. stock never goes below zero (InsufficientStockException),
 *   3. quantity_on_hand and the audit-trail transaction are saved together, or not at all.
 *
 * Quantities are calculated in whole thousandths (12.5 kg = 12500) to avoid decimal rounding errors.
 */
class InventoryService
{
    private const SCALE = 1000; // matches the 3 decimal places in the database

    /**
     * Record one stock movement and update the stock on hand.
     *
     * @param  float|int|string  $quantity  Always positive, EXCEPT count_adjustment which is signed (+ found, - missing).
     * @param  array{stock_transfer_id?: int, production_record_id?: int, stock_count_id?: int}  $links
     */
    public function record(
        InventoryItem $item,
        Location $location,
        string $type,
        float|int|string $quantity,
        User $user,
        ?CarbonInterface $transactionDate = null,
        ?string $remarks = null,
        array $links = [],
    ): InventoryTransaction {
        $amount = self::toUnits($quantity);
        $change = $this->signedChange($type, $amount);

        // DB::transaction runs inside the caller's transaction if there is one (e.g. a transfer
        // with many items), so a failure on any item rolls back ALL of them.
        return DB::transaction(function () use ($item, $location, $type, $amount, $change, $user, $transactionDate, $remarks, $links) {
            $stock = $this->lockStockRow($item, $location);

            $current = self::toUnits($stock->quantity_on_hand);
            $new = $current + $change;

            if ($new < 0) {
                throw InsufficientStockException::for(
                    $item->name,
                    $location->name,
                    self::format($current),
                    self::format(abs($change)),
                    $item->unit_of_measure,
                );
            }

            $stock->quantity_on_hand = self::fromUnits($new);
            $stock->save();

            return InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'user_id' => $user->id,
                'transaction_type' => $type,
                'quantity' => self::fromUnits($type === InventoryTransaction::COUNT_ADJUSTMENT ? $change : $amount),
                'balance_after' => self::fromUnits($new),
                'stock_transfer_id' => $links['stock_transfer_id'] ?? null,
                'production_record_id' => $links['production_record_id'] ?? null,
                'stock_count_id' => $links['stock_count_id'] ?? null,
                'transaction_date' => $transactionDate ?? now(),
                'remarks' => $remarks,
            ]);
        });
    }

    /** Current quantity on hand (0 if the item has never been stocked at this location). */
    public function quantityOnHand(InventoryItem $item, Location $location): string
    {
        return InventoryStock::where('inventory_item_id', $item->id)
            ->where('location_id', $location->id)
            ->value('quantity_on_hand') ?? '0.000';
    }

    /**
     * Get the stock row for this item+location, creating it at 0 if needed, and lock it
     * (SELECT ... FOR UPDATE) until the database transaction finishes.
     */
    private function lockStockRow(InventoryItem $item, Location $location): InventoryStock
    {
        // insertOrIgnore is safe if two requests create the same row at the same time
        // (the unique index on location_id + inventory_item_id rejects the duplicate).
        InventoryStock::insertOrIgnore([
            'location_id' => $location->id,
            'inventory_item_id' => $item->id,
            'quantity_on_hand' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return InventoryStock::where('location_id', $location->id)
            ->where('inventory_item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** Turn a positive amount into + or - depending on the transaction type. */
    private function signedChange(string $type, int $amount): int
    {
        if ($type === InventoryTransaction::COUNT_ADJUSTMENT) {
            if ($amount === 0) {
                throw new InvalidArgumentException('A count adjustment cannot be zero.');
            }

            return $amount; // already signed
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return match (true) {
            in_array($type, InventoryTransaction::INCREASE_TYPES, true) => $amount,
            in_array($type, InventoryTransaction::DECREASE_TYPES, true) => -$amount,
            default => throw new InvalidArgumentException("Unknown transaction type: {$type}"),
        };
    }

    // ---- Exact decimal helpers (thousandths) ----

    public static function toUnits(float|int|string|null $quantity): int
    {
        return (int) round(((float) $quantity) * self::SCALE);
    }

    public static function fromUnits(int $units): string
    {
        return number_format($units / self::SCALE, 3, '.', '');
    }

    /** For messages: 12500 -> "12.5" */
    public static function format(int $units): string
    {
        return rtrim(rtrim(self::fromUnits($units), '0'), '.');
    }
}
