<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current quantity of one item at one location.
 * Do NOT update quantity_on_hand directly in controllers; it must always change together
 * with an InventoryTransaction (an inventory service will handle this in Phase 4).
 */
class InventoryStock extends Model
{
    protected $fillable = ['location_id', 'inventory_item_id', 'quantity_on_hand'];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
