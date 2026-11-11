<?php

namespace App\Exceptions;

use App\Http\Resources\BusinessLeadResource;
use App\Http\Resources\LeadResource;
use App\Models\BusinessLead;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The phone number already belongs to one of the user's CRM leads or researched businesses. */
class DuplicateBusinessException extends RuntimeException
{
    public function __construct(public readonly ?Lead $lead = null, public readonly ?BusinessLead $businessLead = null)
    {
        parent::__construct($businessLead
            ? 'This business (same phone number) is already in your Business Finder list.'
            : 'A CRM lead with this phone number already exists.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'duplicate' => [
                'type' => $this->businessLead ? 'business_lead' : 'lead',
                'lead' => $this->lead ? new LeadResource($this->lead) : null,
                'business_lead' => $this->businessLead ? new BusinessLeadResource($this->businessLead->loadMissing('lead')) : null,
                'can_link' => $this->businessLead === null,
                'hint' => $this->businessLead
                    ? 'Open the existing business to review or update it.'
                    : 'Review the existing lead, or resend with "on_duplicate": "link" to attach this business to it.',
            ],
        ], 409);
    }
}
