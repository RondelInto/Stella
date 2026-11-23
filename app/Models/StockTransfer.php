<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Transfer of stock from the commissary to a branch.
 * pending -> released (stock leaves source) -> received (stock arrives at destination)
 * pending -> cancelled (no stock moved). Released transfers cannot be cancelled (decision T2).
 */
class StockTransfer extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RELEASED = 'released';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    /** Maximum item lines per transfer (keeps forms well under PHP's input limit). */
    public const MAX_ITEMS = 100;

    protected $fillable = [
        'reference_no',
        'source_location_id',
        'destination_location_id',
        'prepared_by',
        'released_by',
        'received_by',
        'transfer_date',
        'released_at',
        'received_at',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'source_location_id' => 'integer',
            'destination_location_id' => 'integer',
            'transfer_date' => 'date',
            'released_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /** Received transfer where at least one item arrived short. */
    public function hasDiscrepancy(): bool
    {
        return $this->isReceived()
            && $this->details->contains(fn (StockTransferDetail $d) => (float) $d->quantity_received !== (float) $d->quantity_sent);
    }

    /** Transfers that involve any of the given locations (as source or destination). */
    public function scopeInvolvingLocations(Builder $query, array $locationIds): void
    {
        $query->where(fn ($q) => $q
            ->whereIn('source_location_id', $locationIds)
            ->orWhereIn('destination_location_id', $locationIds));
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockTransferDetail::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
