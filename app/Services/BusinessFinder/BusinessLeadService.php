<?php

namespace App\Services\BusinessFinder;

use App\Enums\ContactStatus;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\WebsiteStatus;
use App\Exceptions\DuplicateBusinessException;
use App\Models\AuditLog;
use App\Models\BusinessLead;
use App\Models\Lead;
use App\Models\User;
use App\Services\FollowupService;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business Lead Finder ↔ CRM bridge.
 * Saving to the CRM creates a regular Lead (source "Business Finder"); scheduling uses the existing
 * FollowupService, so the one-pending-follow-up rule, auto-scheduling and Sunday skipping all apply.
 */
class BusinessLeadService
{
    public function __construct(private readonly FollowupService $followups) {}

    /**
     * Duplicates of a phone number among the user's CRM leads and researched businesses,
     * plus businesses with the same name in the same city (warning only).
     *
     * @return array{lead: ?Lead, business_lead: ?BusinessLead, similar: Collection<int, BusinessLead>}
     */
    public function findDuplicates(User $user, ?string $phone, ?string $businessName = null, ?string $city = null, ?int $exceptId = null): array
    {
        $digits = Phone::digits($phone);

        $business = $digits ? $user->businessLeads()->where('phone_digits', $digits)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->with('lead')->first() : null;
        $lead = $digits ? $user->leads()->where('mobile_digits', $digits)->first() : null;

        $similar = collect();
        if (trim((string) $businessName) !== '') {
            $similar = $user->businessLeads()
                ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($businessName))])
                ->when(trim((string) $city) !== '', fn ($q) => $q->whereRaw('LOWER(city) = ?', [mb_strtolower(trim($city))]))
                ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
                ->with('lead')->limit(5)->get();
        }

        return ['lead' => $lead, 'business_lead' => $business, 'similar' => $similar];
    }

    /**
     * Create a researched business and (by default) save it to the CRM.
     *
     * @param  array{save_to_crm?: bool, on_duplicate?: string, priority?: ?string, status?: ?string, next_at?: ?Carbon}  $options
     *
     * @throws DuplicateBusinessException
     */
    public function create(User $user, array $data, array $options = []): BusinessLead
    {
        return DB::transaction(function () use ($user, $data, $options) {
            $dup = $this->findDuplicates($user, $data['phone'] ?? null);
            if ($dup['business_lead']) {
                throw new DuplicateBusinessException($dup['lead'] ?? $dup['business_lead']->lead, $dup['business_lead']);
            }
            $saveToCrm = $options['save_to_crm'] ?? true;
            if ($saveToCrm && $dup['lead'] && ($options['on_duplicate'] ?? 'reject') !== 'link') {
                throw new DuplicateBusinessException($dup['lead']);
            }

            $business = $user->businessLeads()->make($this->withDefaults($data));
            $business->save();
            AuditLog::record('business_lead.created', $business, ['business_name' => $business->business_name]);

            if ($saveToCrm) {
                $this->attachLead($business, $dup['lead'], $options);
            }

            return $business->refresh()->load('lead');
        });
    }

    /**
     * Save an already-researched business to the CRM.
     *
     * @throws DuplicateBusinessException
     */
    public function convert(BusinessLead $business, array $options = []): BusinessLead
    {
        return DB::transaction(function () use ($business, $options) {
            $dup = $this->findDuplicates($business->user, $business->phone);
            if ($dup['lead'] && ($options['on_duplicate'] ?? 'reject') !== 'link') {
                throw new DuplicateBusinessException($dup['lead']);
            }
            $this->attachLead($business, $dup['lead'], $options);

            return $business->refresh()->load('lead');
        });
    }

    /**
     * Update business details. Fields that are mirrored in the CRM lead (business name → company,
     * contact person → name, phone → mobile, email) are synced when they change.
     *
     * @throws DuplicateBusinessException
     */
    public function update(BusinessLead $business, array $data): BusinessLead
    {
        return DB::transaction(function () use ($business, $data) {
            if (array_key_exists('phone', $data) && Phone::digits($data['phone']) !== $business->phone_digits) {
                $dup = $this->findDuplicates($business->user, $data['phone'], exceptId: $business->id);
                $otherLead = $dup['lead'] && $dup['lead']->id !== $business->lead_id ? $dup['lead'] : null;
                if ($dup['business_lead'] || $otherLead) {
                    throw new DuplicateBusinessException($otherLead, $dup['business_lead']);
                }
            }
            if (($data['contact_status'] ?? null) === ContactStatus::Verified->value && empty($data['contact_verified_at']) && ! $business->contact_verified_at) {
                $data['contact_verified_at'] = today()->toDateString();
            }

            $business->fill($data)->save();
            $changed = array_keys($business->getChanges());
            $this->syncLead($business, $changed);
            AuditLog::record('business_lead.updated', $business, ['fields' => array_values(array_diff($changed, ['updated_at', 'phone_digits']))]);

            return $business->refresh()->load('lead');
        });
    }

    /** Record a manual contact verification by the salesperson. */
    public function verifyContact(BusinessLead $business, array $data): BusinessLead
    {
        $status = ContactStatus::from($data['verification_status']);
        $update = array_filter([
            'phone' => $data['phone'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $data['email'] ?? null,
            'contact_source' => $data['contact_source'] ?? null,
            'source_url' => $data['source_url'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $update['contact_status'] = $status->value;
        $update['contact_verified_at'] = $data['verification_date'] ?? today()->toDateString();
        if (! empty($data['notes'])) {
            $stamp = Carbon::parse($update['contact_verified_at'])->format('d M Y');
            $update['notes'] = trim(($business->notes ? $business->notes."\n" : '')."[$stamp] Contact {$status->value}: {$data['notes']}");
        }

        $business = $this->update($business, $update);
        AuditLog::record('business_lead.contact_verified', $business, ['status' => $status->value]);

        return $business;
    }

    /** Store a website-check result. The status is only changed when the site clearly responded. */
    public function applyWebsiteCheck(BusinessLead $business, array $result, ?string $url = null): BusinessLead
    {
        $business->forceFill([
            'website_url' => $url ?? $result['url'] ?? $business->website_url,
            'website_checked_at' => now(),
            'website_http_status' => $result['http_status'],
            'website_check_result' => $result['result'],
            'website_check_message' => mb_substr($result['message'], 0, 255),
        ]);
        if ($result['suggested_status'] === WebsiteStatus::Available->value
            && in_array($business->website_status, [WebsiteStatus::Pending, WebsiteStatus::NoWebsite, WebsiteStatus::Inactive], true)) {
            $business->website_status = WebsiteStatus::Available;
        }
        $business->save();
        AuditLog::record('business_lead.website_checked', $business, ['result' => $result['result'], 'http_status' => $result['http_status']]);

        return $business->refresh()->load('lead');
    }

    /** CRM lead fields for a business. */
    public function leadAttributes(BusinessLead $b, array $options = []): array
    {
        $context = array_filter([
            'Category: '.$b->category,
            ($b->city || $b->district) ? 'Location: '.implode(', ', array_filter([$b->area, $b->city, $b->district])) : null,
            $b->website_url ? 'Website: '.$b->website_url.' ('.$b->website_status->value.')' : 'Website: '.$b->website_status->value,
        ]);

        return [
            'name' => $b->contact_person ?: $b->business_name,
            'company' => $b->business_name,
            'mobile' => $b->phone,
            'email' => $b->email,
            'source' => LeadSource::BusinessFinder,
            'status' => $options['status'] ?? LeadStatus::New->value,
            'priority' => $options['priority'] ?? LeadPriority::Medium->value,
            'notes' => trim(($b->notes ? $b->notes."\n\n" : '').'[Business Finder] '.implode(' | ', $context)),
        ];
    }

    private function attachLead(BusinessLead $business, ?Lead $existing, array $options): void
    {
        if ($existing) {
            $lead = $existing;
            AuditLog::record('business_lead.linked_to_lead', $business, ['lead_id' => $lead->id]);
        } else {
            $lead = $business->user->leads()->create($this->leadAttributes($business, $options));
            AuditLog::record('business_lead.saved_to_crm', $business, ['lead_id' => $lead->id]);
        }
        $business->forceFill(['lead_id' => $lead->id])->save();

        if ($business->do_not_contact) {
            $this->markDoNotContact($lead, true);
        } elseif (! empty($options['next_at'])) {
            $this->followups->syncNextFollowup($lead, $options['next_at']);
        }
    }

    /** Mirror changed business fields onto the linked CRM lead. */
    private function syncLead(BusinessLead $business, array $changed): void
    {
        $lead = $business->lead;
        if (! $lead) {
            return;
        }
        $map = ['business_name' => 'company', 'contact_person' => 'name', 'phone' => 'mobile', 'email' => 'email'];
        $attrs = [];
        foreach ($map as $from => $to) {
            if (in_array($from, $changed, true) && ($business->$from !== null || $to !== 'name')) {
                $attrs[$to] = $business->$from;
            }
        }
        if ($attrs) {
            $lead->update($attrs);
        }
        if (in_array('do_not_contact', $changed, true)) {
            $this->markDoNotContact($lead, $business->do_not_contact);
        }
    }

    private function markDoNotContact(Lead $lead, bool $dnc): void
    {
        $lead->forceFill(['do_not_contact' => $dnc])->save();
        if ($dnc) {
            $this->followups->syncNextFollowup($lead, null);   // stop reminders
        }
        AuditLog::record($dnc ? 'lead.do_not_contact' : 'lead.contact_allowed', $lead);
    }

    private function withDefaults(array $data): array
    {
        $data['state'] ??= config('business_finder.state');
        if (($data['contact_status'] ?? null) === ContactStatus::Verified->value && empty($data['contact_verified_at'])) {
            $data['contact_verified_at'] = today()->toDateString();
        }

        return $data;
    }
}
