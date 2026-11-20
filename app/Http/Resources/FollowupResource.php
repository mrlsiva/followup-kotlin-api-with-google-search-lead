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
        new OA\Property(property: 'call_direction', type: 'string', nullable: true, enum: ['outgoing', 'incoming']),
        new OA\Property(property: 'phone_number', type: 'string', nullable: true, example: '+919876543210'),
        new OA\Property(property: 'call_started_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'call_ended_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'call_duration', type: 'integer', nullable: true, example: 185, description: 'Seconds talked'),
        new OA\Property(property: 'has_recording', type: 'boolean'),
        new OA\Property(property: 'recording', type: 'object', nullable: true, description: 'Call recording; fetch `url` with the Bearer token', properties: [
            new OA\Property(property: 'url', type: 'string', example: 'http://localhost/followup-api-kotlin/public/api/followups/40/recording'),
            new OA\Property(property: 'format', type: 'string', example: 'm4a'),
            new OA\Property(property: 'mime', type: 'string', example: 'audio/mp4'),
            new OA\Property(property: 'size', type: 'integer', example: 482113, description: 'Bytes'),
        ]),
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
            'call_direction' => $this->call_direction?->value,
            'phone_number' => $this->phone_number,
            'call_started_at' => $this->call_started_at?->toIso8601String(),
            'call_ended_at' => $this->call_ended_at?->toIso8601String(),
            'call_duration' => $this->call_duration,
            'has_recording' => $this->hasRecording(),
            'recording' => $this->hasRecording() ? [
                'url' => route('api.followups.recording.show', $this->id),
                'format' => $this->recordingFormat(),
                'mime' => $this->recording_mime,
                'size' => $this->recording_size,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
