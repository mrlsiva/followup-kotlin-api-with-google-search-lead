<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Followup',
    description: 'Pending rows are scheduled reminders; completed rows are timeline notes.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 40),
        new OA\Property(property: 'lead_id', type: 'integer', example: 12),
        new OA\Property(property: 'lead', type: 'object', nullable: true, description: 'Included on follow-up list endpoints', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'mobile', type: 'string', nullable: true),
            new OA\Property(property: 'company', type: 'string', nullable: true),
            new OA\Property(property: 'status', type: 'string'),
            new OA\Property(property: 'priority', type: 'string'),
        ]),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'completed']),
        new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-11-12', description: 'Date of the note (completed) or due date (pending)'),
        new OA\Property(property: 'time', type: 'string', example: '15:30'),
        new OA\Property(property: 'is_overdue', type: 'boolean', description: 'Pending and past its time — show in red'),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'call_status', type: 'string', nullable: true, enum: ['Interested', 'Not Answered', 'Callback', 'Closed']),
        new OA\Property(property: 'note', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class FollowupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $when = $this->completed_at ?? $this->scheduled_at;

        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'mobile' => $this->lead->mobile,
                'company' => $this->lead->company,
                'status' => $this->lead->status?->value,
                'priority' => $this->lead->priority?->value,
            ]),
            'status' => $this->status->value,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'date' => $when->format('Y-m-d'),
            'time' => $when->format('H:i'),
            'is_overdue' => $this->isOverdue(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'call_status' => $this->call_status?->value,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
