<?php

namespace App\Services\BusinessFinder;

use App\Enums\BusinessCategory;
use App\Enums\ContactStatus;
use App\Enums\LeadStatus;
use App\Enums\WebsiteStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/** Filters shared by the Business Finder list and export endpoints. */
class BusinessLeadQuery
{
    public const SCOPES = ['all', 'verified_contacts', 'no_website', 'followups'];
    public const CONTACT_FILTERS = ['phone_available', 'phone_missing', 'pending', 'verified'];

    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(BusinessCategory::class)],
            'city' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:60'],
            'website_status' => ['nullable', Rule::enum(WebsiteStatus::class)],
            'contact_status' => ['nullable', Rule::enum(ContactStatus::class)],
            'contact' => ['nullable', Rule::in(self::CONTACT_FILTERS)],
            'saved' => ['nullable', 'boolean'],
            'lead_status' => ['nullable', Rule::enum(LeadStatus::class)],
            'scope' => ['nullable', Rule::in(self::SCOPES)],
            'ids' => ['nullable', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'include_do_not_contact' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['latest', 'oldest', 'name', 'city'])],
        ];
    }

    public static function build(User $user, array $f): Builder
    {
        $q = $user->businessLeads()->getQuery()
            ->search($f['search'] ?? null)
            ->when($f['category'] ?? null, fn ($q, $v) => $v === BusinessCategory::All->value ? $q : $q->where('category', $v))
            ->when($f['city'] ?? null, fn ($q, $v) => $q->where('city', $v))
            ->when($f['district'] ?? null, fn ($q, $v) => $q->where('district', $v))
            ->when($f['website_status'] ?? null, fn ($q, $v) => $q->where('website_status', $v))
            ->when($f['contact_status'] ?? null, fn ($q, $v) => $q->where('contact_status', $v))
            ->contactFilter($f['contact'] ?? null)
            ->when(isset($f['saved']) && $f['saved'] !== null, fn ($q) => filter_var($f['saved'], FILTER_VALIDATE_BOOLEAN) ? $q->saved() : $q->notSaved())
            ->when($f['lead_status'] ?? null, fn ($q, $v) => $q->whereHas('lead', fn ($l) => $l->where('status', $v)))
            ->when(! empty($f['ids']), fn ($q) => $q->whereKey($f['ids']));

        match ($f['scope'] ?? 'all') {
            'verified_contacts' => $q->where('contact_status', ContactStatus::Verified),
            'no_website' => $q->where('website_status', WebsiteStatus::NoWebsite),
            'followups' => $q->whereHas('lead', fn ($l) => $l->whereNotNull('next_followup_at')),
            default => null,
        };

        match ($f['sort'] ?? 'latest') {
            'oldest' => $q->oldest(),
            'name' => $q->orderBy('business_name'),
            'city' => $q->orderBy('city')->orderBy('business_name'),
            default => $q->latest(),
        };

        return $q;
    }
}
