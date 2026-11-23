<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use LogicException;

/**
 * A read-only copy of an earlier version of an SOP (team decision S2).
 */
class SopVersion extends Model
{
    protected $fillable = [
        'sop_record_id',
        'version_no',
        'title',
        'description',
        'content',
        'effective_date',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'version_no' => 'integer',
        ];
    }

    /** Old versions are history: they can't be changed. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('SOP versions are read-only.'));
    }

    public function renderedContent(): HtmlString
    {
        return SopRecord::render($this->content);
    }

    public function sopRecord(): BelongsTo
    {
        return $this->belongsTo(SopRecord::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
