<?php

namespace App\Http\Controllers\Api\BusinessFinder;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessFinder\BusinessLeadRequest;
use App\Http\Requests\BusinessFinder\ConvertBusinessLeadRequest;
use App\Http\Requests\BusinessFinder\VerifyContactRequest;
use App\Http\Requests\BusinessFinder\VerifyWebsiteRequest;
use App\Http\Resources\BusinessLeadResource;
use App\Http\Resources\LeadResource;
use App\Models\AuditLog;
use App\Models\BusinessLead;
use App\Services\BusinessFinder\BusinessLeadQuery;
use App\Services\BusinessFinder\BusinessLeadService;
use App\Services\BusinessFinder\WebsiteChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'BusinessLeadInput', properties: [
    new OA\Property(property: 'business_name', type: 'string', example: 'Sri Lakshmi Hospital', description: 'Required on create'),
    new OA\Property(property: 'category', type: 'string', example: 'Hospitals', description: 'Any category except "All businesses" (default "Other businesses")'),
    new OA\Property(property: 'contact_person', type: 'string', nullable: true, example: 'Dr. Senthil'),
    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+91 452 234 5678', description: 'Public business phone'),
    new OA\Property(property: 'email', type: 'string', nullable: true),
    new OA\Property(property: 'address', type: 'string', nullable: true),
    new OA\Property(property: 'area', type: 'string', nullable: true, example: 'Anna Nagar'),
    new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Madurai'),
    new OA\Property(property: 'district', type: 'string', nullable: true, example: 'Madurai'),
    new OA\Property(property: 'pincode', type: 'string', nullable: true, example: '625020'),
    new OA\Property(property: 'source_url', type: 'string', nullable: true, description: 'Where the contact was found (Maps listing, website…)'),
    new OA\Property(property: 'website_url', type: 'string', nullable: true, example: 'srilakshmihospital.in', description: 'https:// is added when missing'),
    new OA\Property(property: 'website_status', type: 'string', enum: ['No website found', 'Website available', 'Website outdated', 'Website inactive', 'Pending verification'], example: 'Pending verification'),
    new OA\Property(property: 'contact_status', type: 'string', enum: ['Pending', 'Verified', 'Incorrect number', 'Disconnected', 'Do not contact'], example: 'Pending'),
    new OA\Property(property: 'contact_source', type: 'string', nullable: true, example: 'Google Maps listing'),
    new OA\Property(property: 'contact_verified_at', type: 'string', format: 'date', nullable: true),
    new OA\Property(property: 'notes', type: 'string', nullable: true),
    new OA\Property(property: 'business_search_id', type: 'integer', nullable: true, description: 'search_id from /search-links'),
    new OA\Property(property: 'save_to_crm', type: 'boolean', default: true, description: 'Create only. false = keep as researched, save later with /convert'),
    new OA\Property(property: 'on_duplicate', type: 'string', enum: ['reject', 'link'], default: 'reject', description: 'Create only. link = attach to the existing CRM lead with the same phone'),
    new OA\Property(property: 'priority', type: 'string', enum: ['High', 'Medium', 'Low'], default: 'Medium', description: 'Create only, CRM lead priority'),
    new OA\Property(property: 'lead_status', type: 'string', enum: ['New', 'Interested', 'Follow-up', 'Won', 'Lost'], default: 'New', description: 'Create only, CRM lead status'),
    new OA\Property(property: 'next_followup_date', type: 'string', format: 'date', nullable: true, example: '2026-11-12', description: 'Create only: first call'),
    new OA\Property(property: 'next_followup_time', type: 'string', nullable: true, example: '11:00'),
    new OA\Property(property: 'next_followup_at', type: 'string', format: 'date-time', nullable: true),
])]
#[OA\Response(response: 'DuplicateBusiness', description: 'Same phone number already exists', content: new OA\JsonContent(properties: [
    new OA\Property(property: 'message', type: 'string', example: 'A CRM lead with this phone number already exists.'),
    new OA\Property(property: 'duplicate', type: 'object', properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['lead', 'business_lead']),
        new OA\Property(property: 'lead', ref: '#/components/schemas/Lead', nullable: true),
        new OA\Property(property: 'business_lead', ref: '#/components/schemas/BusinessLead', nullable: true),
        new OA\Property(property: 'can_link', type: 'boolean', description: 'Resend with on_duplicate=link to attach to the existing lead'),
        new OA\Property(property: 'hint', type: 'string'),
    ]),
]))]
class BusinessLeadController extends Controller
{
    public function __construct(private readonly BusinessLeadService $service) {}

    #[OA\Get(
        path: '/api/business-finder/leads', summary: 'Saved / researched businesses', tags: ['Business Finder leads'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Business name, contact person, phone, email, city or area', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'city', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'district', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'website_status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['No website found', 'Website available', 'Website outdated', 'Website inactive', 'Pending verification'])),
            new OA\Parameter(name: 'contact_status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['Pending', 'Verified', 'Incorrect number', 'Disconnected', 'Do not contact'])),
            new OA\Parameter(name: 'contact', in: 'query', schema: new OA\Schema(type: 'string', enum: ['phone_available', 'phone_missing', 'pending', 'verified'])),
            new OA\Parameter(name: 'saved', in: 'query', description: '1 = saved to CRM, 0 = not yet', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'lead_status', in: 'query', description: 'Status of the linked CRM lead', schema: new OA\Schema(type: 'string', enum: ['New', 'Interested', 'Follow-up', 'Won', 'Lost'])),
            new OA\Parameter(name: 'scope', in: 'query', schema: new OA\Schema(type: 'string', default: 'all', enum: ['all', 'verified_contacts', 'no_website', 'followups'])),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', default: 'latest', enum: ['latest', 'oldest', 'name', 'city'])),
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/per_page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated businesses', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/BusinessLead')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(BusinessLeadQuery::rules());

        return BusinessLeadResource::collection(
            BusinessLeadQuery::build($request->user(), $filters)->with('lead')
                ->paginate($this->perPage())->withQueryString()
        );
    }

    #[OA\Post(
        path: '/api/business-finder/leads', summary: 'Add Business to CRM',
        description: "Saves the researched business and (unless `save_to_crm=false`) creates a CRM lead in the existing leads table:\n\n"
            ."| Business field | CRM lead field |\n|---|---|\n| Business name | company |\n| Contact person (or business name) | name |\n| Phone | mobile |\n| Email | email |\n| — | source = `Business Finder` |\n| Category, location, website | appended to notes |\n| — | status `New`, priority `Medium` (overridable) |\n\n"
            .'Send `next_followup_date` / `next_followup_time` to schedule the first call through the existing follow-up system. '
            .'A phone number already used by one of your CRM leads returns **409** with the existing lead; resend with `on_duplicate=link` to attach to it.',
        tags: ['Business Finder leads'], security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BusinessLeadInput')),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/BusinessLead'),
                new OA\Property(property: 'lead', ref: '#/components/schemas/Lead', nullable: true),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 409, ref: '#/components/responses/DuplicateBusiness'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(BusinessLeadRequest $request): JsonResponse
    {
        $business = $this->service->create($request->user(), $request->businessData(), $request->crmOptions());

        return response()->json($this->payload($business), 201);
    }

    #[OA\Get(
        path: '/api/business-finder/leads/{id}', summary: 'Business details with the linked CRM lead and its follow-up timeline', tags: ['Business Finder leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/BusinessLead'),
                new OA\Property(property: 'lead', ref: '#/components/schemas/Lead', nullable: true, description: 'Full CRM lead with followups timeline'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(BusinessLead $businessLead): JsonResponse
    {
        return response()->json($this->payload($businessLead, withTimeline: true));
    }

    #[OA\Put(
        path: '/api/business-finder/leads/{id}', summary: 'Edit a business (send only the fields to change)',
        description: 'Business name, contact person, phone and email changes are copied to the linked CRM lead.',
        tags: ['Business Finder leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BusinessLeadInput')),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/BusinessLead'),
                new OA\Property(property: 'lead', ref: '#/components/schemas/Lead', nullable: true),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/DuplicateBusiness'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function update(BusinessLeadRequest $request, BusinessLead $businessLead): JsonResponse
    {
        $business = $this->service->update($businessLead, $request->businessData());

        return response()->json($this->payload($business));
    }

    #[OA\Delete(
        path: '/api/business-finder/leads/{id}', summary: 'Remove a researched business',
        description: 'The linked CRM lead (if any) is kept; delete it from /api/leads if needed.',
        tags: ['Business Finder leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Business removed.')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(BusinessLead $businessLead): JsonResponse
    {
        $businessLead->delete();
        AuditLog::record('business_lead.deleted', $businessLead, ['business_name' => $businessLead->business_name]);

        return response()->json(['message' => 'Business removed.']);
    }

    #[OA\Post(
        path: '/api/business-finder/leads/{id}/convert', summary: 'Save a researched business to the CRM',
        description: 'Creates the CRM lead (same mapping as Add Business to CRM) and optionally schedules the first call. 409 when already saved or when the phone belongs to another lead (use on_duplicate=link).',
        tags: ['Business Finder leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'on_duplicate', type: 'string', enum: ['reject', 'link'], default: 'reject'),
            new OA\Property(property: 'priority', type: 'string', enum: ['High', 'Medium', 'Low'], default: 'Medium'),
            new OA\Property(property: 'lead_status', type: 'string', enum: ['New', 'Interested', 'Follow-up', 'Won', 'Lost'], default: 'New'),
            new OA\Property(property: 'next_followup_date', type: 'string', format: 'date', nullable: true, example: '2026-11-12'),
            new OA\Property(property: 'next_followup_time', type: 'string', nullable: true, example: '11:00'),
            new OA\Property(property: 'next_followup_at', type: 'string', format: 'date-time', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Saved to CRM', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/BusinessLead'),
                new OA\Property(property: 'lead', ref: '#/components/schemas/Lead'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/DuplicateBusiness'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function convert(ConvertBusinessLeadRequest $request, BusinessLead $businessLead): JsonResponse
    {
        if ($businessLead->isSavedToCrm()) {
            return response()->json([
                'message' => 'This business is already saved to the CRM. Schedule calls with PUT /api/leads/{id}.',
                'lead' => new LeadResource($businessLead->lead),
            ], 409);
        }
        $business = $this->service->convert($businessLead, $request->options());

        return response()->json($this->payload($business));
    }

    #[OA\Post(
        path: '/api/business-finder/leads/{id}/verify-contact', summary: 'Record a manual contact verification',
        description: "The salesperson confirms the details. Nothing is verified automatically.\n\n`Do not contact` cancels the pending follow-up of the linked CRM lead and blocks new ones; the record is left out of exports.",
        tags: ['Business Finder leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['verification_status'], properties: [
            new OA\Property(property: 'verification_status', type: 'string', enum: ['Pending', 'Verified', 'Incorrect number', 'Disconnected', 'Do not contact']),
            new OA\Property(property: 'phone', type: 'string', nullable: true),
            new OA\Property(property: 'contact_person', type: 'string', nullable: true),
            new OA\Property(property: 'email', type: 'string', nullable: true),
            new OA\Property(property: 'contact_source', type: 'string', nullable: true, example: 'Called the listed number'),
            new OA\Property(property: 'source_url', type: 'string', nullable: true),
            new OA\Property(property: 'verification_date', type: 'string', format: 'date', nullable: true, description: 'Default today'),
            new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Spoke to reception, owner available after 5 pm'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/BusinessLead'),
                new OA\Property(property: 'lead', ref: '#/components/schemas/Lead', nullable: true),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/DuplicateBusiness'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function verifyContact(VerifyContactRequest $request, BusinessLead $businessLead): JsonResponse
    {
        $business = $this->service->verifyContact($businessLead, $request->validated());

        return response()->json($this->payload($business));
    }

    #[OA\Post(
        path: '/api/business-finder/leads/{id}/verify-website', summary: 'Check the business website and store the result',
        description: 'Uses the saved website (or `url`, which is then saved). The website status is changed only when the site clearly responds (→ Website available); otherwise the result is stored and the salesperson decides.',
        tags: ['Business Finder leads'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'url', type: 'string', nullable: true)])),
        responses: [
            new OA\Response(response: 200, description: 'Checked', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/BusinessLead'),
                new OA\Property(property: 'check', ref: '#/components/schemas/WebsiteCheck'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, description: 'Too many checks'),
        ]
    )]
    public function verifyWebsite(VerifyWebsiteRequest $request, BusinessLead $businessLead, WebsiteChecker $checker): JsonResponse
    {
        $result = $checker->check($request->input('url') ?: $businessLead->website_url);
        if (in_array($result['result'], [WebsiteChecker::INVALID_URL, WebsiteChecker::NOT_ALLOWED], true)) {
            return response()->json(['message' => $result['message'], 'errors' => ['url' => [$result['message']]], 'check' => $result], 422);
        }
        $business = $this->service->applyWebsiteCheck($businessLead, $result, $request->filled('url') ? $result['url'] : null);

        return response()->json(['data' => new BusinessLeadResource($business), 'check' => $result]);
    }

    private function payload(BusinessLead $business, bool $withTimeline = false): array
    {
        $business->loadMissing('lead');
        $lead = $business->lead;
        if ($lead && $withTimeline) {
            $lead->load(['followups' => fn ($q) => $q
                ->orderByRaw("status = 'pending' DESC")
                ->orderByRaw('COALESCE(completed_at, scheduled_at) DESC')]);
        }

        return [
            'data' => new BusinessLeadResource($business),
            'lead' => $lead ? new LeadResource($lead) : null,
        ];
    }
}
