<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Lead',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'name', type: 'string', example: 'Ravi Kumar'),
        new OA\Property(property: 'mobile', type: 'string', nullable: true, example: '+91 98765 43210'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'ravi@acme.in'),
        new OA\Property(property: 'company', type: 'string', nullable: true, example: 'Acme Traders'),
        new OA\Property(property: 'source', type: 'string', enum: ['WhatsApp', 'Facebook', 'Website', 'Referral', 'Call', 'Walk-in', 'Other']),
        new OA\Property(property: 'status', type: 'string', enum: ['New', 'Interested', 'Follow-up', 'Won', 'Lost']),
        new OA\Property(property: 'priority', type: 'string', enum: ['High', 'Medium', 'Low']),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'do_not_contact', type: 'boolean', description: 'Set from Business Finder contact verification; no follow-ups are scheduled while true'),
        new OA\Property(property: 'business_lead', ref: '#/components/schemas/BusinessLead', nullable: true, description: 'Business Finder details (detail endpoint only, when the lead came from Business Finder)'),
        new OA\Property(property: 'next_followup_at', type: 'string', format: 'date-time', nullable: true, example: '2026-11-12T15:30:00+05:30'),
        new OA\Property(property: 'next_followup_date', type: 'string', format: 'date', nullable: true, example: '2026-11-12'),
        new OA\Property(property: 'next_followup_time', type: 'string', nullable: true, example: '15:30'),
        new OA\Property(property: 'is_overdue', type: 'boolean'),
        new OA\Property(property: 'followups_count', type: 'integer', description: 'Completed follow-up notes (list endpoints)'),
        new OA\Property(property: 'followups', type: 'array', items: new OA\Items(ref: '#/components/schemas/Followup'), description: 'Timeline (detail endpoint only)'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $next = $this->next_followup_at;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'company' => $this->company,
            'source' => $this->source?->value,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'notes' => $this->notes,
            'do_not_contact' => (bool) $this->do_not_contact,
            'business_lead' => $this->whenLoaded('businessLead', fn () => $this->businessLead ? new BusinessLeadResource($this->businessLead) : null),
            'next_followup_at' => $next?->toIso8601String(),
            'next_followup_date' => $next?->format('Y-m-d'),
            'next_followup_time' => $next?->format('H:i'),
            'is_overdue' => $next !== null && $next->isPast(),
            'followups_count' => $this->whenCounted('followups'),
            'followups' => FollowupResource::collection($this->whenLoaded('followups')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
