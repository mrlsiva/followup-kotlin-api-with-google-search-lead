<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'BusinessLead',
    description: 'A business researched in the Business Lead Finder. When saved to the CRM it is linked to a regular lead (lead_id).',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 7),
        new OA\Property(property: 'business_name', type: 'string', example: 'Sri Lakshmi Hospital'),
        new OA\Property(property: 'category', type: 'string', example: 'Hospitals'),
        new OA\Property(property: 'contact_person', type: 'string', nullable: true, example: 'Dr. Senthil'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 452 234 5678'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'area', type: 'string', nullable: true, example: 'Anna Nagar'),
        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Madurai'),
        new OA\Property(property: 'district', type: 'string', nullable: true, example: 'Madurai'),
        new OA\Property(property: 'pincode', type: 'string', nullable: true, example: '625020'),
        new OA\Property(property: 'state', type: 'string', example: 'Tamil Nadu'),
        new OA\Property(property: 'source_url', type: 'string', nullable: true, description: 'Where the public contact details were found'),
        new OA\Property(property: 'website_url', type: 'string', nullable: true, example: 'https://srilakshmihospital.in/'),
        new OA\Property(property: 'website_status', type: 'string', enum: ['No website found', 'Website available', 'Website outdated', 'Website inactive', 'Pending verification']),
        new OA\Property(property: 'website_checked_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'website_http_status', type: 'integer', nullable: true, example: 200),
        new OA\Property(property: 'website_check_result', type: 'string', nullable: true, enum: ['reachable', 'http_error', 'unreachable', 'blocked_by_robots', 'too_many_redirects', 'invalid_url', 'not_allowed']),
        new OA\Property(property: 'website_check_message', type: 'string', nullable: true),
        new OA\Property(property: 'contact_status', type: 'string', enum: ['Pending', 'Verified', 'Incorrect number', 'Disconnected', 'Do not contact']),
        new OA\Property(property: 'contact_source', type: 'string', nullable: true, example: 'Google Maps listing'),
        new OA\Property(property: 'contact_verified_at', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'do_not_contact', type: 'boolean'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'saved_to_crm', type: 'boolean'),
        new OA\Property(property: 'lead_id', type: 'integer', nullable: true),
        new OA\Property(property: 'lead', type: 'object', nullable: true, description: 'CRM lead summary', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'status', type: 'string'),
            new OA\Property(property: 'priority', type: 'string'),
            new OA\Property(property: 'next_followup_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'next_followup_date', type: 'string', format: 'date', nullable: true),
            new OA\Property(property: 'next_followup_time', type: 'string', nullable: true),
            new OA\Property(property: 'is_overdue', type: 'boolean'),
        ]),
        new OA\Property(property: 'business_search_id', type: 'integer', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class BusinessLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lead = $this->relationLoaded('lead') ? $this->lead : null;
        $next = $lead?->next_followup_at;

        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'category' => $this->category?->value,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'area' => $this->area,
            'city' => $this->city,
            'district' => $this->district,
            'pincode' => $this->pincode,
            'state' => $this->state,
            'source_url' => $this->source_url,
            'website_url' => $this->website_url,
            'website_status' => $this->website_status?->value,
            'website_checked_at' => $this->website_checked_at?->toIso8601String(),
            'website_http_status' => $this->website_http_status,
            'website_check_result' => $this->website_check_result,
            'website_check_message' => $this->website_check_message,
            'contact_status' => $this->contact_status?->value,
            'contact_source' => $this->contact_source,
            'contact_verified_at' => $this->contact_verified_at?->format('Y-m-d'),
            'do_not_contact' => (bool) $this->do_not_contact,
            'notes' => $this->notes,
            'saved_to_crm' => $this->relationLoaded('lead') ? $lead !== null : $this->lead_id !== null,
            'lead_id' => $this->relationLoaded('lead') ? $lead?->id : $this->lead_id,
            'lead' => $lead ? [
                'id' => $lead->id,
                'name' => $lead->name,
                'status' => $lead->status?->value,
                'priority' => $lead->priority?->value,
                'next_followup_at' => $next?->toIso8601String(),
                'next_followup_date' => $next?->format('Y-m-d'),
                'next_followup_time' => $next?->format('H:i'),
                'is_overdue' => $next !== null && $next->isPast(),
            ] : null,
            'business_search_id' => $this->business_search_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
