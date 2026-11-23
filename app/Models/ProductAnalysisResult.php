<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnalysisResult extends Model
{
    protected $fillable = [
        'analysis_run_id',
        'product_id',
        'total_quantity_sold',
        'total_sales',
        'unit_cost_used',
        'total_cost',
        'gross_profit',
        'profit_margin',
        'sales_frequency',
        'cluster_label',
    ];

    protected function casts(): array
    {
        return [
            'total_quantity_sold' => 'decimal:3',
            'total_sales' => 'decimal:2',
            'unit_cost_used' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'profit_margin' => 'decimal:4',
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
}
