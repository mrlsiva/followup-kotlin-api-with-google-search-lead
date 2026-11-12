<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Moves a lead (with its follow-ups and Business Finder record) to another salesperson. */
class LeadOwnershipService
{
    public function reassign(Lead $lead, User $to): Lead
    {
        if ($lead->user_id === $to->id) {
            return $lead;
        }
        if ($lead->mobile_digits && $to->leads()->where('mobile_digits', $lead->mobile_digits)->exists()) {
            throw new RuntimeException(__(':name already has a lead with this phone number.', ['name' => $to->name]));
        }

        return DB::transaction(function () use ($lead, $to) {
            $from = $lead->user_id;
            $lead->forceFill(['user_id' => $to->id])->save();
            Followup::where('lead_id', $lead->id)->update(['user_id' => $to->id]);
            $lead->businessLead()->update(['user_id' => $to->id, 'business_search_id' => null]);
            AuditLog::record('lead.reassigned', $lead, ['from_user_id' => $from, 'to_user_id' => $to->id]);

            return $lead->refresh();
        });
    }
}
