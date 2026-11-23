<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Employee role. Use the slug constants in code, never the display name.
 */
class Role extends Model
{
    public const ADMIN = 'admin';                       // Management (Admin)
    public const BRANCH_MANAGER = 'branch_manager';
    public const COMMISSARY_STAFF = 'commissary_staff';
    public const BRANCH_STAFF = 'branch_staff';

    protected $fillable = ['name', 'slug', 'description', 'responsibilities'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['assigned_at', 'is_active'])
            ->withTimestamps();
    }

    /** SOPs that employees with this role are allowed to view. */
    public function sopRecords(): BelongsToMany
    {
        return $this->belongsToMany(SopRecord::class)->withTimestamps();
    }
}
