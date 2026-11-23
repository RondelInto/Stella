<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferDetail extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'inventory_item_id',
        'quantity_sent',
        'quantity_received',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity_sent' => 'decimal:3',
            'quantity_received' => 'decimal:3',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /** Received minus sent (negative = items missing on arrival). NULL until received. */
    public function discrepancy(): ?float
    {
        return $this->quantity_received === null
            ? null
            : (float) $this->quantity_received - (float) $this->quantity_sent;
    }
}
