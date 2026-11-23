<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisRun extends Model
{
    public const TYPE_PROFITABILITY = 'profitability';
    public const TYPE_FREQUENCY = 'frequency';
    public const TYPE_CLUSTERING = 'clustering';
    public const TYPE_REGRESSION = 'regression';

    protected $fillable = [
        'run_by',
        'analysis_type',
        'period_start',
        'period_end',
        'model_parameters',
        'accuracy_score',
        'run_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'model_parameters' => 'array',
            'accuracy_score' => 'decimal:4',
            'run_at' => 'datetime',
        ];
    }

    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    /** Import batches used as input for this run. */
    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(SalesImportBatch::class, 'analysis_run_batch')->withTimestamps();
    }

    public function results(): HasMany
    {
        return $this->hasMany(ProductAnalysisResult::class);
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(ProductionForecast::class);
    }
}
