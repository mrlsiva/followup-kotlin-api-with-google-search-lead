<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\FollowupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'LeadInput', properties: [
    new OA\Property(property: 'name', type: 'string', example: 'Ravi Kumar', description: 'Required on create'),
    new OA\Property(property: 'mobile', type: 'string', nullable: true, example: '+91 98765 43210'),
    new OA\Property(property: 'email', type: 'string', nullable: true, example: 'ravi@acme.in'),
    new OA\Property(property: 'company', type: 'string', nullable: true, example: 'Acme Traders'),
    new OA\Property(property: 'source', type: 'string', enum: ['WhatsApp', 'Facebook', 'Website', 'Referral', 'Call', 'Walk-in', 'Other'], example: 'WhatsApp'),
    new OA\Property(property: 'status', type: 'string', enum: ['New', 'Interested', 'Follow-up', 'Won', 'Lost'], example: 'New'),
    new OA\Property(property: 'priority', type: 'string', enum: ['High', 'Medium', 'Low'], example: 'High'),
    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Wants a quote for 200 units'),
    new OA\Property(property: 'next_followup_date', type: 'string', format: 'date', nullable: true, example: '2026-11-12', description: 'Send null to clear the schedule'),
    new OA\Property(property: 'next_followup_time', type: 'string', nullable: true, example: '15:30', description: 'HH:MM 24h, default 10:00'),
    new OA\Property(property: 'next_followup_at', type: 'string', format: 'date-time', nullable: true, description: 'Alternative to date + time'),
])]
class LeadController extends Controller
{
    public function __construct(private readonly FollowupService $followups) {}

    #[OA\Get(
        path: '/api/leads', summary: 'List / search leads', tags: ['Leads'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Name, company, phone or email', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['New', 'Interested', 'Follow-up', 'Won', 'Lost'])),
            new OA\Parameter(name: 'priority', in: 'query', schema: new OA\Schema(type: 'string', enum: ['High', 'Medium', 'Low'])),
            new OA\Parameter(name: 'source', in: 'query', schema: new OA\Schema(type: 'string', enum: ['WhatsApp', 'Facebook', 'Website', 'Referral', 'Call', 'Walk-in', 'Other'])),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', default: 'latest', enum: ['latest', 'oldest', 'name', 'next_followup', 'priority'])),
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/per_page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated leads', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Lead')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'sort' => ['nullable', 'in:latest,oldest,name,next_followup,priority'],
        ]);

        $query = $request->user()->leads()
            ->search($request->input('search', $request->input('q')))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->priority, fn ($q, $v) => $q->where('priority', $v))
            ->when($request->source, fn ($q, $v) => $q->where('source', $v))
            ->withCount(['followups' => fn ($q) => $q->completed()]);

        match ($request->input('sort', 'latest')) {
            'oldest' => $query->oldest(),
            'name' => $query->orderBy('name'),
            'next_followup' => $query->orderByRaw('next_followup_at IS NULL')->orderBy('next_followup_at'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END")->latest(),
            default => $query->latest('updated_at'),
        };

        return LeadResource::collection($query->paginate($this->perPage())->withQueryString());
    }

    #[OA\Post(
        path: '/api/leads', summary: 'Add a new lead', tags: ['Leads'], security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LeadInput')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Lead')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(LeadRequest $request): JsonResponse
    {
        $lead = DB::transaction(function () use ($request) {
            $lead = $request->user()->leads()->create($request->leadData());
            $this->followups->syncNextFollowup($lead, $request->nextFollowupAt());

            return $lead;
        });

        return (new LeadResource($lead->refresh()->load('followups')))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/leads/{lead}', summary: 'Lead details with follow-up timeline', tags: ['Leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Lead')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(Lead $lead): LeadResource
    {
        return new LeadResource($lead->load(['followups' => fn ($q) => $q
            ->orderByRaw("status = 'pending' DESC")
            ->orderByRaw('COALESCE(completed_at, scheduled_at) DESC'),
        ]));
    }

    #[OA\Put(
        path: '/api/leads/{lead}', summary: 'Edit a lead (send only the fields to change)', tags: ['Leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LeadInput')),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Lead')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function update(LeadRequest $request, Lead $lead): LeadResource
    {
        DB::transaction(function () use ($request, $lead) {
            $lead->update($request->leadData());
            if ($request->hasNextFollowupInput()) {
                $this->followups->syncNextFollowup($lead, $request->nextFollowupAt());
            }
        });

        return $this->show($lead->refresh());
    }

    #[OA\Delete(
        path: '/api/leads/{lead}', summary: 'Delete a lead and its follow-ups', tags: ['Leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Lead deleted.')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(Lead $lead): JsonResponse
    {
        DB::transaction(function () use ($lead) {
            $lead->followups()->pending()->delete();   // stop reminders
            $lead->forceFill(['next_followup_at' => null])->save();
            $lead->delete();                            // soft delete keeps history recoverable
        });

        return response()->json(['message' => 'Lead deleted.']);
    }
}
