<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRecord extends Model
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'location_id',
        'product_id',
        'user_id',
        'production_forecast_id',
        'production_date',
        'quantity_produced',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'quantity_produced' => 'decimal:3',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Who recorded the production. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The forecast that guided this production, if any. */
    public function productionForecast(): BelongsTo
    {
        return $this->belongsTo(ProductionForecast::class);
    }

    /** Ingredients used. */
    public function details(): HasMany
    {
        return $this->hasMany(ProductionDetail::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
