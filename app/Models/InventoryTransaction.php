<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Audit trail of every stock movement. Records can be created but never changed or deleted.
 */
class InventoryTransaction extends Model
{
    public const RECEIVED = 'received';
    public const ISSUED = 'issued';
    public const PRODUCTION_USE = 'production_use';
    public const PRODUCTION_OUTPUT = 'production_output';
    public const TRANSFER_OUT = 'transfer_out';
    public const TRANSFER_IN = 'transfer_in';
    public const DAMAGED = 'damaged';
    public const COUNT_ADJUSTMENT = 'count_adjustment';

    /** Types that increase stock. */
    public const INCREASE_TYPES = [self::RECEIVED, self::PRODUCTION_OUTPUT, self::TRANSFER_IN];

    /** Types that decrease stock. */
    public const DECREASE_TYPES = [self::ISSUED, self::PRODUCTION_USE, self::TRANSFER_OUT, self::DAMAGED];

    protected $fillable = [
        'inventory_item_id',
        'location_id',
        'user_id',
        'transaction_type',
        'quantity',
        'balance_after',
        'stock_transfer_id',
        'production_record_id',
        'stock_count_id',
        'transaction_date',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'transaction_date' => 'datetime',
        ];
    }

    /**
     * Protect the audit trail: block edits and deletes at the model level.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Inventory transactions cannot be edited. Record a correcting transaction instead.');
        });

        static::deleting(function () {
            throw new LogicException('Inventory transactions cannot be deleted.');
        });
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function productionRecord(): BelongsTo
    {
        return $this->belongsTo(ProductionRecord::class);
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }
}
