<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A closing inventory count at one location (added to the ERD for discrepancy tracking).
 */
class StockCount extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';

    /** Maximum items in one count (PHP accepts ~1,000 form fields; larger counts are split by category). */
    public const MAX_ITEMS = 300;

    protected $fillable = ['location_id', 'counted_by', 'count_date', 'status', 'completed_at', 'remarks'];

    protected function casts(): array
    {
        return [
            'count_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockCountDetail::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
