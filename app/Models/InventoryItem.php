<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryItem extends Model
{
    public const TYPE_INGREDIENT = 'ingredient';
    public const TYPE_IN_PREPARATION = 'in_preparation';
    public const TYPE_FINISHED_PRODUCT = 'finished_product';

    protected $fillable = [
        'inventory_category_id',
        'item_code',
        'name',
        'item_type',
        'unit_of_measure',
        'unit_cost',
        'reorder_level',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'reorder_level' => 'decimal:3',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /** Stock of this item at every location. */
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /** The product sold as this item (only for finished products). */
    public function product(): HasOne
    {
        return $this->hasOne(Product::class);
    }

    public function transferDetails(): HasMany
    {
        return $this->hasMany(StockTransferDetail::class);
    }

    public function productionDetails(): HasMany
    {
        return $this->hasMany(ProductionDetail::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
