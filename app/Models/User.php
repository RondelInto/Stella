<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'contact_no',
        'account_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Full name, e.g. "Juan Dela Cruz". Read-only: $user->name
     * (keeps existing views that use ->name working).
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /** The branch or commissary this user is assigned to (NULL for Management). */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** All roles ever assigned to this user (check pivot->is_active). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_at', 'is_active'])
            ->withTimestamps();
    }

    /** Only the roles that are currently active. */
    public function activeRoles(): BelongsToMany
    {
        return $this->roles()->wherePivot('is_active', true);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function preparedTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'prepared_by');
    }

    public function receivedTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'received_by');
    }

    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Check if the user has an ACTIVE role, by slug.
     * Example: $user->hasRole('admin') or $user->hasRole(['branch_manager', 'branch_staff'])
     */
    public function hasRole(string|array $slugs): bool
    {
        return $this->activeRoles->whereIn('slug', (array) $slugs)->isNotEmpty();
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }
}
