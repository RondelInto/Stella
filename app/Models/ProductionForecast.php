<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Predicted production quantity. An ESTIMATE for planning, not guaranteed demand.
 */
class ProductionForecast extends Model
{
    protected $fillable = [
        'analysis_run_id',
        'product_id',
        'location_id',
        'forecast_date',
        'predicted_quantity',
    ];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'predicted_quantity' => 'decimal:3',
        ];
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Production records that were guided by this forecast. */
    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }
}
