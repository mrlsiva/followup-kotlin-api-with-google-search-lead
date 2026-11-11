<?php

namespace App\Models;

use App\Enums\BusinessCategory;
use App\Enums\ContactStatus;
use App\Enums\WebsiteStatus;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business researched in the Business Lead Finder. When saved to the CRM it is linked to a
 * regular Lead (lead_id); calls and follow-ups then use the existing lead/follow-up system.
 */
class BusinessLead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_search_id', 'business_name', 'category', 'contact_person', 'phone', 'email',
        'address', 'area', 'city', 'district', 'pincode', 'state', 'source_url', 'website_url',
        'website_status', 'contact_status', 'contact_source', 'contact_verified_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => BusinessCategory::class,
            'website_status' => WebsiteStatus::class,
            'contact_status' => ContactStatus::class,
            'website_checked_at' => 'datetime',
            'contact_verified_at' => 'date',
            'do_not_contact' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BusinessLead $b) {
            $b->phone_digits = Phone::digits($b->phone);
            // "Do not contact" is driven by the contact status, so the two never disagree.
            $b->do_not_contact = $b->contact_status === ContactStatus::DoNotContact;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The CRM lead (null when not saved to the CRM yet, or when that lead was deleted). */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(BusinessSearch::class, 'business_search_id');
    }

    public function isSavedToCrm(): bool
    {
        return $this->lead_id !== null && $this->lead !== null;
    }

    // ---------- Scopes ----------

    /** Business name, contact person, phone, email, city or area. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('business_name', 'like', $like)
            ->orWhere('contact_person', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('city', 'like', $like)
            ->orWhere('area', 'like', $like));
    }

    /** Saved to the CRM (linked to a lead that still exists). */
    public function scopeSaved(Builder $q): Builder
    {
        return $q->whereHas('lead');
    }

    public function scopeNotSaved(Builder $q): Builder
    {
        return $q->whereDoesntHave('lead');
    }

    /** Contact filter: phone_available | phone_missing | pending | verified. */
    public function scopeContactFilter(Builder $q, ?string $filter): Builder
    {
        return match ($filter) {
            'phone_available' => $q->whereNotNull('phone_digits'),
            'phone_missing' => $q->whereNull('phone_digits'),
            'pending' => $q->where('contact_status', ContactStatus::Pending),
            'verified' => $q->where('contact_status', ContactStatus::Verified),
            default => $q,
        };
    }

    /** Records that may be exported for outreach (never do-not-contact). */
    public function scopeContactable(Builder $q): Builder
    {
        return $q->where('do_not_contact', false);
    }
}
