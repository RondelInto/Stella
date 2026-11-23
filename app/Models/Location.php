<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A branch (Matina-Pangi, Catalunan Pequeño, Puan) or the Central Commissary.
 */
class Location extends Model
{
    public const TYPE_BRANCH = 'branch';
    public const TYPE_COMMISSARY = 'commissary';

    protected $fillable = ['name', 'type', 'address', 'contact_no', 'status'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'source_location_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'destination_location_id');
    }

    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class);
    }

    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    public function salesImportBatches(): HasMany
    {
        return $this->hasMany(SalesImportBatch::class);
    }

    public function productionForecasts(): HasMany
    {
        return $this->hasMany(ProductionForecast::class);
    }

    public function isCommissary(): bool
    {
        return $this->type === self::TYPE_COMMISSARY;
    }

    /** Usage: Location::active()->get() */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
