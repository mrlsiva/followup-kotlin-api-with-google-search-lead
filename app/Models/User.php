<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Defaults for new models (match the column defaults). */
    protected $attributes = [
        'role' => 'sales',
        'is_active' => true,
        'locale' => 'en',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    // ---------- Relations ----------

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(Followup::class);
    }

    public function businessLeads(): HasMany
    {
        return $this->hasMany(BusinessLead::class);
    }

    public function businessSearches(): HasMany
    {
        return $this->hasMany(BusinessSearch::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Salespeople assigned to this manager. */
    public function team(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    // ---------- Roles & visibility ----------

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    /** Admins and managers see other people's work and team reports. */
    public function canViewTeam(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    /**
     * IDs of users whose records this user may VIEW (null = everyone).
     * Editing is always limited to the owner.
     *
     * @return list<int>|null
     */
    public function visibleUserIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }
        if ($this->isManager()) {
            return [$this->id, ...$this->team()->pluck('id')->all()];
        }

        return [$this->id];
    }

    public function canSeeUser(int $userId): bool
    {
        $ids = $this->visibleUserIds();

        return $ids === null || in_array($userId, $ids, true);
    }

    /** Users this user can pick in filters / reassign to. */
    public function visibleUsers(): Builder
    {
        $ids = $this->visibleUserIds();

        return User::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('name');
    }

    /** May move this record to another salesperson (admin: anyone; manager: within their team). */
    public function canReassign(int $ownerId): bool
    {
        return $this->isAdmin() || ($this->isManager() && $this->canSeeUser($ownerId));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
