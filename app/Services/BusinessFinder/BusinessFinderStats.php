<?php

namespace App\Services\BusinessFinder;

use App\Enums\ContactStatus;
use App\Enums\LeadStatus;
use App\Enums\WebsiteStatus;
use App\Models\User;
use Illuminate\Support\Str;

/** Business Finder counters (Business Finder dashboard + block on the main CRM dashboard). */
class BusinessFinderStats
{
    public function stats(User $user): array
    {
        $b = fn () => $user->businessLeads();
        $byWebsite = $b()->selectRaw('website_status, COUNT(*) as total')->groupBy('website_status')->pluck('total', 'website_status');
        $byContact = $b()->selectRaw('contact_status, COUNT(*) as total')->groupBy('contact_status')->pluck('total', 'contact_status');
        $byLeadStatus = $user->leads()->whereHas('businessLead')
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'total_researched' => $b()->count(),
            'saved_to_crm' => $b()->saved()->count(),
            'no_website' => (int) ($byWebsite[WebsiteStatus::NoWebsite->value] ?? 0),
            'website_available' => (int) ($byWebsite[WebsiteStatus::Available->value] ?? 0),
            'website_outdated' => (int) ($byWebsite[WebsiteStatus::Outdated->value] ?? 0),
            'website_inactive' => (int) ($byWebsite[WebsiteStatus::Inactive->value] ?? 0),
            'contact_verified' => (int) ($byContact[ContactStatus::Verified->value] ?? 0),
            'do_not_contact' => (int) ($byContact[ContactStatus::DoNotContact->value] ?? 0),
            'pending_verification' => $b()->where(fn ($q) => $q
                ->where('website_status', WebsiteStatus::Pending)
                ->orWhere('contact_status', ContactStatus::Pending))->count(),
            'new_leads' => (int) ($byLeadStatus[LeadStatus::New->value] ?? 0),
            'interested' => (int) ($byLeadStatus[LeadStatus::Interested->value] ?? 0),
            'converted' => (int) ($byLeadStatus[LeadStatus::Won->value] ?? 0),
            'searches' => $user->businessSearches()->count(),
        ];
    }

    /** One-line text summary for the dashboard card. */
    public function summary(array $s): string
    {
        if ($s['total_researched'] === 0) {
            return 'No businesses researched yet. Start with a search in Business Finder.';
        }

        $n = fn (int $count, string $word) => $count.' '.Str::plural($word, $count);

        return sprintf(
            '%s researched, %d saved to CRM. %d with no website, %s verified, %d pending verification. %d interested, %d converted.',
            $n($s['total_researched'], 'business'), $s['saved_to_crm'], $s['no_website'],
            $n($s['contact_verified'], 'contact'), $s['pending_verification'], $s['interested'], $s['converted']
        );
    }
}
