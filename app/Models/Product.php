<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A finished food product sold to customers.
 * Its cost comes from the linked inventory item's unit_cost.
 */
class Product extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'pos_product_code',
        'pos_product_name',
        'selling_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
        ];
    }

    /** The finished-product inventory item this product is "sold as". */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    public function importedPosSales(): HasMany
    {
        return $this->hasMany(ImportedPosSale::class);
    }

    public function analysisResults(): HasMany
    {
        return $this->hasMany(ProductAnalysisResult::class);
    }

    public function productionForecasts(): HasMany
    {
        return $this->hasMany(ProductionForecast::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
