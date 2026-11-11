<?php

namespace App\Models;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Support\Phone;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'mobile', 'email', 'company', 'source', 'status', 'priority', 'notes', 'next_followup_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'priority' => LeadPriority::class,
            'next_followup_at' => 'datetime',
            'do_not_contact' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Comparable phone digits for duplicate detection (Business Lead Finder).
        static::saving(fn (Lead $lead) => $lead->mobile_digits = Phone::digits($lead->mobile));
    }

    /** The Business Lead Finder record this lead was saved from, if any. */
    public function businessLead(): HasOne
    {
        return $this->hasOne(BusinessLead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(Followup::class);
    }

    /** Search by name, company or phone. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('company', 'like', $like)
            ->orWhere('mobile', 'like', $like)
            ->orWhere('email', 'like', $like));
    }
}
