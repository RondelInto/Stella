<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Standard Operating Procedure.
 * Draft -> Active -> Archived. Staff see an SOP only when it is Active, its effective date has
 * arrived (or none is set), and it is assigned to one of their active roles (team decision S4).
 */
class SopRecord extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'sop_code',
        'title',
        'category',
        'description',
        'content',
        'version_no',
        'effective_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'version_no' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Active, but its effective date is still in the future (staff can't see it yet). */
    public function isScheduled(): bool
    {
        return $this->isActive() && $this->effective_date?->isFuture();
    }

    /**
     * The procedure as safe HTML (team decision S1: simple formatting with Markdown).
     * Raw HTML typed into the text is removed, and unsafe links (e.g. javascript:) are blocked.
     */
    public function renderedContent(): HtmlString
    {
        return self::render($this->content);
    }

    public static function render(?string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown((string) $markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    /**
     * SOPs the user may see:
     *  - Management (manage-sops): every SOP, any status.
     *  - Everyone else: Active, effective date reached (or none), assigned to one of their active roles.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('manage-sops')) {
            return;
        }

        $roleIds = $user->activeRoles->pluck('id');

        $query->where('status', self::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('effective_date')->orWhereDate('effective_date', '<=', today()))
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Roles allowed to view this SOP. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /** Earlier versions, newest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(SopVersion::class)->orderByDesc('version_no');
    }
}
