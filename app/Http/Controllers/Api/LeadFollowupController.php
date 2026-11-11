<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FollowupNoteRequest;
use App\Http\Resources\FollowupResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\FollowupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'FollowupNoteInput', required: ['call_status'], properties: [
    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-11-10', description: 'When the call happened (default today)'),
    new OA\Property(property: 'time', type: 'string', example: '11:15', description: 'HH:MM 24h (default now)'),
    new OA\Property(property: 'note', type: 'string', example: 'Asked for revised pricing, call back Friday'),
    new OA\Property(property: 'call_status', type: 'string', enum: ['Interested', 'Not Answered', 'Callback', 'Closed']),
    new OA\Property(property: 'schedule_next', type: 'boolean', nullable: true, description: "true = auto-schedule next follow-up from call status + priority; false = no next follow-up; omitted = keep the current schedule (notes) / auto-schedule (complete)"),
    new OA\Property(property: 'next_followup_date', type: 'string', format: 'date', nullable: true, example: '2026-11-14', description: 'Explicit next follow-up (overrides schedule_next)'),
    new OA\Property(property: 'next_followup_time', type: 'string', nullable: true, example: '11:00'),
    new OA\Property(property: 'next_followup_at', type: 'string', format: 'date-time', nullable: true),
])]
class LeadFollowupController extends Controller
{
    public function __construct(private readonly FollowupService $followups) {}

    #[OA\Get(
        path: '/api/leads/{lead}/followups', summary: 'Follow-up history (timeline) of a lead', tags: ['Lead follow-ups'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Pending follow-up first, then notes newest first', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Followup')),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function index(Lead $lead): AnonymousResourceCollection
    {
        return FollowupResource::collection(
            $lead->followups()
                ->orderByRaw("status = 'pending' DESC")
                ->orderByRaw('COALESCE(completed_at, scheduled_at) DESC')
                ->get()
        );
    }

    #[OA\Post(
        path: '/api/leads/{lead}/followups', summary: 'Add a follow-up note (date, time, note, status after call)', tags: ['Lead follow-ups'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/FollowupNoteInput')),
        responses: [
            new OA\Response(response: 201, description: 'Note added', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Followup'),
                new OA\Property(property: 'lead', ref: '#/components/schemas/Lead'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(FollowupNoteRequest $request, Lead $lead): JsonResponse
    {
        $note = $this->followups->addNote($lead, $request->serviceData());

        return response()->json([
            'data' => new FollowupResource($note),
            'lead' => new LeadResource($lead->refresh()),
        ], 201);
    }
}
