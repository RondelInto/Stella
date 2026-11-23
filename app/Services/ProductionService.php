<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records one production batch at the commissary (Module 6).
 *
 * In ONE database transaction:
 *   - each ingredient used is deducted   (production_use)
 *   - the finished product is added      (production_output, on the product's linked item)
 * If any ingredient lacks stock, InsufficientStockException is thrown and NOTHING is saved.
 *
 * Ingredient quantities are entered by staff for every batch; the system assumes no recipes.
 * Records are never edited or cancelled (team decision P2): mistakes are fixed by a stock count.
 */
class ProductionService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * @param  array<int, array{inventory_item_id: int, quantity: string}>  $ingredients
     */
    public function record(
        User $user,
        Location $location,
        Product $product,
        string $quantityProduced,
        CarbonInterface $productionDate,
        array $ingredients,
        ?string $remarks = null,
    ): ProductionRecord {
        $output = $product->inventoryItem;

        if (! $output) {
            throw new InvalidArgumentException("Product {$product->pos_product_name} is not linked to an inventory item.");
        }

        // Stock movements are dated now for today's batches; a past batch is dated at the end of that day.
        $movementDate = $productionDate->isToday() ? now() : Carbon::parse($productionDate)->endOfDay();

        return DB::transaction(function () use ($user, $location, $product, $output, $quantityProduced, $productionDate, $ingredients, $remarks, $movementDate) {
            $record = ProductionRecord::create([
                'location_id' => $location->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'production_date' => $productionDate->toDateString(),
                'quantity_produced' => $quantityProduced,
                'status' => ProductionRecord::STATUS_COMPLETED,
                'remarks' => $remarks,
            ]);

            $note = "Production #{$record->id}: {$product->pos_product_name}";
            $items = InventoryItem::whereIn('id', array_column($ingredients, 'inventory_item_id'))->get()->keyBy('id');

            foreach ($ingredients as $line) {
                $item = $items[$line['inventory_item_id']];

                $record->details()->create([
                    'inventory_item_id' => $item->id,
                    'quantity_used' => $line['quantity'],
                ]);

                $this->inventory->record(
                    $item, $location, InventoryTransaction::PRODUCTION_USE, $line['quantity'], $user,
                    $movementDate, $note, ['production_record_id' => $record->id],
                );
            }

            $this->inventory->record(
                $output, $location, InventoryTransaction::PRODUCTION_OUTPUT, $quantityProduced, $user,
                $movementDate, $note, ['production_record_id' => $record->id],
            );

            return $record;
        });
    }
}
