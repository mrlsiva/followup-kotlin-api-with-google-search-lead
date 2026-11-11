<?php

namespace App\Http\Controllers\Api;

use App\Enums\FollowupStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FollowupNoteRequest;
use App\Http\Requests\RescheduleFollowupRequest;
use App\Http\Resources\FollowupResource;
use App\Http\Resources\LeadResource;
use App\Models\Followup;
use App\Services\FollowupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

class FollowupController extends Controller
{
    public function __construct(private readonly FollowupService $followups) {}

    #[OA\Get(
        path: '/api/followups', summary: 'Follow-up lists: today, upcoming, overdue, pending, completed', tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', description: 'today = pending scheduled today (past ones flagged is_overdue); upcoming = after today; overdue = pending past due', schema: new OA\Schema(type: 'string', default: 'pending', enum: ['today', 'upcoming', 'overdue', 'pending', 'completed'])),
            new OA\Parameter(name: 'from', in: 'query', description: 'Filter scheduled/completed date ≥ (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', description: 'Filter scheduled/completed date ≤ (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Lead name, company or phone', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/per_page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated follow-ups with lead summary', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Followup')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'type' => ['nullable', 'in:today,upcoming,overdue,pending,completed'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $type = $request->input('type', 'pending');
        $dateCol = $type === 'completed' ? 'completed_at' : 'scheduled_at';

        $query = $request->user()->followups()->activeLead()->with('lead')
            ->when($request->filled('search'), fn ($q) => $q->whereHas('lead', fn ($l) => $l->search($request->search)))
            ->when($request->from, fn ($q, $v) => $q->where($dateCol, '>=', Carbon::parse($v)->startOfDay()))
            ->when($request->to, fn ($q, $v) => $q->where($dateCol, '<=', Carbon::parse($v)->endOfDay()));

        match ($type) {
            'today' => $query->today()->orderBy('scheduled_at'),
            'upcoming' => $query->upcoming()->orderBy('scheduled_at'),
            'overdue' => $query->overdue()->orderBy('scheduled_at'),
            'completed' => $query->completed()->latest('completed_at'),
            default => $query->pending()->orderBy('scheduled_at'),
        };

        return FollowupResource::collection($query->paginate($this->perPage())->withQueryString());
    }

    #[OA\Get(
        path: '/api/followups/reminders', summary: 'Pending follow-ups in a time window — for scheduling local notifications',
        description: 'The app can call this after login/sync and schedule an Android local notification for each item. Defaults to the next 7 days.',
        tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', description: 'ISO date-time (default now)', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'days', in: 'query', description: 'Window length in days (1–30)', schema: new OA\Schema(type: 'integer', default: 7)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Followup')),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function reminders(Request $request): AnonymousResourceCollection
    {
        $request->validate(['from' => ['nullable', 'date'], 'days' => ['nullable', 'integer', 'between:1,30']]);
        $from = $request->filled('from') ? Carbon::parse($request->from) : now();
        $to = $from->copy()->addDays((int) $request->input('days', 7));

        return FollowupResource::collection(
            $request->user()->followups()->activeLead()->pending()->with('lead')
                ->whereBetween('scheduled_at', [$from, $to])
                ->orderBy('scheduled_at')->get()
        );
    }

    #[OA\Get(
        path: '/api/followups/daily-summary', summary: 'Daily reminder text for pending follow-ups', tags: ['Follow-ups'], security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'date', type: 'string', format: 'date'),
                new OA\Property(property: 'today', type: 'integer', example: 4),
                new OA\Property(property: 'overdue', type: 'integer', example: 2),
                new OA\Property(property: 'pending', type: 'integer', example: 15),
                new OA\Property(property: 'should_notify', type: 'boolean'),
                new OA\Property(property: 'title', type: 'string', example: 'Pending follow-ups'),
                new OA\Property(property: 'message', type: 'string', example: 'You have 4 follow-ups due today and 2 overdue.'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function dailySummary(Request $request): JsonResponse
    {
        $base = fn () => $request->user()->followups()->activeLead();
        $today = $base()->today()->count();
        $overdue = $base()->overdue()->count();
        $pending = $base()->pending()->count();

        $parts = array_filter([
            $today ? "$today follow-up".($today > 1 ? 's' : '').' due today' : null,
            $overdue ? "$overdue overdue" : null,
        ]);

        return response()->json([
            'date' => today()->toDateString(),
            'today' => $today,
            'overdue' => $overdue,
            'pending' => $pending,
            'should_notify' => $today + $overdue > 0,
            'title' => 'Pending follow-ups',
            'message' => $parts ? 'You have '.implode(' and ', $parts).'.' : 'No follow-ups due today.',
        ]);
    }

    #[OA\Get(
        path: '/api/followups/{followup}', summary: 'Follow-up details', tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'followup', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Followup')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(Followup $followup): FollowupResource
    {
        return new FollowupResource($followup->load('lead'));
    }

    #[OA\Post(
        path: '/api/followups/{followup}/complete', summary: 'Mark a follow-up completed and schedule the next one automatically',
        description: "Records the call outcome. Unless `schedule_next=false` or an explicit next date is sent, the next follow-up is auto-scheduled:\n\n"
            ."| Status after call | Next follow-up |\n|---|---|\n| Not Answered / Callback | +1 day |\n| Interested | +1 day (High), +3 (Medium), +5 (Low) |\n| Closed | none |\n\nThe original time of day is kept and Sundays are skipped.",
        tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'followup', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['call_status'], properties: [
            new OA\Property(property: 'call_status', type: 'string', enum: ['Interested', 'Not Answered', 'Callback', 'Closed']),
            new OA\Property(property: 'note', type: 'string', example: 'Spoke to Ravi, sending quotation'),
            new OA\Property(property: 'schedule_next', type: 'boolean', default: true),
            new OA\Property(property: 'next_followup_date', type: 'string', format: 'date', nullable: true, example: '2026-11-14'),
            new OA\Property(property: 'next_followup_time', type: 'string', nullable: true, example: '11:00'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Completed', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Followup'),
                new OA\Property(property: 'next_followup', ref: '#/components/schemas/Followup', nullable: true),
                new OA\Property(property: 'lead', ref: '#/components/schemas/Lead'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Already completed'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function complete(FollowupNoteRequest $request, Followup $followup): JsonResponse
    {
        if (! $followup->isPending()) {
            return response()->json(['message' => 'This follow-up is already completed.'], 409);
        }
        $done = $this->followups->complete($followup, $request->serviceData());
        $lead = $done->lead->refresh();
        $next = $lead->followups()->pending()->orderBy('scheduled_at')->first();

        return response()->json([
            'data' => new FollowupResource($done),
            'next_followup' => $next ? new FollowupResource($next) : null,
            'lead' => new LeadResource($lead),
        ]);
    }

    #[OA\Put(
        path: '/api/followups/{followup}', summary: 'Reschedule a pending follow-up', tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'followup', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'next_followup_date', type: 'string', format: 'date', example: '2026-11-15'),
            new OA\Property(property: 'next_followup_time', type: 'string', example: '16:00'),
            new OA\Property(property: 'next_followup_at', type: 'string', format: 'date-time', description: 'Alternative to date + time'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Rescheduled', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Followup')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Completed follow-ups cannot be rescheduled'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function update(RescheduleFollowupRequest $request, Followup $followup): JsonResponse|FollowupResource
    {
        if (! $followup->isPending()) {
            return response()->json(['message' => 'Completed follow-ups cannot be rescheduled.'], 409);
        }
        $this->followups->syncNextFollowup($followup->lead, $request->nextFollowupAt());
        $pending = $followup->lead->followups()->pending()->first();

        return new FollowupResource(($pending ?? $followup)->load('lead'));
    }

    #[OA\Delete(
        path: '/api/followups/{followup}', summary: 'Delete a note or cancel a pending follow-up', tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'followup', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Follow-up deleted.')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(Followup $followup): JsonResponse
    {
        if ($followup->status === FollowupStatus::Pending) {
            $this->followups->syncNextFollowup($followup->lead, null);
        } else {
            $followup->delete();
        }

        return response()->json(['message' => 'Follow-up deleted.']);
    }
}
