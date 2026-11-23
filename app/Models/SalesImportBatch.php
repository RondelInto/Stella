<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One imported POS export file (from one location's POS).
 */
class SalesImportBatch extends Model
{
    protected $fillable = [
        'location_id',
        'imported_by',
        'file_name',
        'period_start',
        'period_end',
        'imported_at',
        'total_records',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'imported_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ImportedPosSale::class);
    }

    public function analysisRuns(): BelongsToMany
    {
        return $this->belongsToMany(AnalysisRun::class, 'analysis_run_batch')->withTimestamps();
    }

    /** Decision PI7: a batch can be deleted only while no analysis run has used it. */
    public function canBeDeleted(): bool
    {
        return ! $this->analysisRuns()->exists();
    }
}
