<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionDetail extends Model
{
    protected $fillable = ['production_record_id', 'inventory_item_id', 'quantity_used'];

    protected function casts(): array
    {
        return [
            'quantity_used' => 'decimal:3',
        ];
    }

    public function productionRecord(): BelongsTo
    {
        return $this->belongsTo(ProductionRecord::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
