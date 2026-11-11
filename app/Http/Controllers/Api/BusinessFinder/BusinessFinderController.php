<?php

namespace App\Http\Controllers\Api\BusinessFinder;

use App\Enums\BusinessCategory;
use App\Enums\ContactStatus;
use App\Enums\WebsiteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessFinder\SearchLinksRequest;
use App\Http\Requests\BusinessFinder\VerifyWebsiteRequest;
use App\Http\Resources\BusinessLeadResource;
use App\Http\Resources\BusinessSearchResource;
use App\Models\AuditLog;
use App\Services\BusinessFinder\BusinessFinderStats;
use App\Services\BusinessFinder\BusinessLeadQuery;
use App\Services\BusinessFinder\BusinessLeadService;
use App\Services\BusinessFinder\SearchLinkBuilder;
use App\Services\BusinessFinder\WebsiteChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'SearchLinks', description: 'Free manual-research links. Open them in the browser / Google Maps app; nothing is scraped.', properties: [
    new OA\Property(property: 'query', type: 'string', example: 'Hospitals in Chennai'),
    new OA\Property(property: 'target_count', type: 'integer', example: 20, description: 'Research goal only (20 / 50 / 100)'),
    new OA\Property(property: 'google_maps_url', type: 'string', example: 'https://www.google.com/maps/search/?api=1&query=Hospitals%20in%20Chennai'),
    new OA\Property(property: 'google_maps_app_uri', type: 'string', example: 'geo:0,0?q=Hospitals%20in%20Chennai', description: 'Android intent URI that opens the Maps app'),
    new OA\Property(property: 'google_search_url', type: 'string', example: 'https://www.google.com/search?q=Hospitals+in+Chennai+contact+number'),
    new OA\Property(property: 'links', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['maps', 'contact', 'website', 'directory']),
        new OA\Property(property: 'label', type: 'string', example: 'Contact numbers'),
        new OA\Property(property: 'url', type: 'string'),
    ])),
    new OA\Property(property: 'result_pages', type: 'array', description: 'Google result pages (10 per page) up to the target count', items: new OA\Items(properties: [
        new OA\Property(property: 'page', type: 'integer'), new OA\Property(property: 'url', type: 'string'),
    ])),
    new OA\Property(property: 'instructions', type: 'string'),
])]
#[OA\Schema(schema: 'WebsiteCheck', description: 'Result of the optional website availability check. A failed check never means the website does not exist.', properties: [
    new OA\Property(property: 'input_url', type: 'string', example: 'srilakshmihospital.in'),
    new OA\Property(property: 'url', type: 'string', nullable: true, example: 'https://srilakshmihospital.in/'),
    new OA\Property(property: 'final_url', type: 'string', nullable: true, description: 'After redirects'),
    new OA\Property(property: 'result', type: 'string', enum: ['reachable', 'http_error', 'unreachable', 'blocked_by_robots', 'too_many_redirects', 'invalid_url', 'not_allowed']),
    new OA\Property(property: 'reachable', type: 'boolean'),
    new OA\Property(property: 'http_status', type: 'integer', nullable: true, example: 200),
    new OA\Property(property: 'ssl_valid', type: 'boolean', nullable: true),
    new OA\Property(property: 'redirects', type: 'integer', example: 1),
    new OA\Property(property: 'response_time_ms', type: 'integer', nullable: true),
    new OA\Property(property: 'suggested_status', type: 'string', nullable: true, example: 'Website available', description: 'Only set when the site clearly responded'),
    new OA\Property(property: 'hints', type: 'array', items: new OA\Items(type: 'string')),
    new OA\Property(property: 'message', type: 'string'),
    new OA\Property(property: 'checked_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'cached', type: 'boolean', description: 'Same URL checked in the last 10 minutes'),
])]
#[OA\Schema(schema: 'BusinessFinderStats', properties: [
    new OA\Property(property: 'total_researched', type: 'integer', example: 42),
    new OA\Property(property: 'saved_to_crm', type: 'integer', example: 30),
    new OA\Property(property: 'no_website', type: 'integer', example: 18),
    new OA\Property(property: 'website_available', type: 'integer', example: 12),
    new OA\Property(property: 'website_outdated', type: 'integer', example: 4),
    new OA\Property(property: 'website_inactive', type: 'integer', example: 2),
    new OA\Property(property: 'contact_verified', type: 'integer', example: 20),
    new OA\Property(property: 'do_not_contact', type: 'integer', example: 1),
    new OA\Property(property: 'pending_verification', type: 'integer', example: 9),
    new OA\Property(property: 'new_leads', type: 'integer', example: 15),
    new OA\Property(property: 'interested', type: 'integer', example: 6),
    new OA\Property(property: 'converted', type: 'integer', example: 2, description: 'Linked CRM leads with status Won'),
    new OA\Property(property: 'searches', type: 'integer', example: 25),
])]
class BusinessFinderController extends Controller
{
    #[OA\Get(
        path: '/api/business-finder/options', summary: 'Dropdown values for the Business Finder screens', tags: ['Business Finder'], security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'categories', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'quantities', type: 'array', items: new OA\Items(type: 'integer'), example: [20, 50, 100]),
                new OA\Property(property: 'website_statuses', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'website_filters', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'contact_statuses', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'contact_filters', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'value', type: 'string', example: 'phone_available'),
                    new OA\Property(property: 'label', type: 'string', example: 'Phone available'),
                ])),
                new OA\Property(property: 'export_scopes', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'state', type: 'string', example: 'Tamil Nadu'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function options(): JsonResponse
    {
        return response()->json([
            'categories' => BusinessCategory::values(),
            'quantities' => config('business_finder.quantities'),
            'website_statuses' => WebsiteStatus::values(),
            'website_filters' => ['All', ...WebsiteStatus::values()],
            'contact_statuses' => ContactStatus::values(),
            'contact_filters' => [
                ['value' => 'phone_available', 'label' => 'Phone available'],
                ['value' => 'phone_missing', 'label' => 'Phone missing'],
                ['value' => 'pending', 'label' => 'Contact verification pending'],
                ['value' => 'verified', 'label' => 'Contact verified'],
            ],
            'export_scopes' => BusinessLeadQuery::SCOPES,
            'state' => config('business_finder.state'),
        ]);
    }

    #[OA\Get(
        path: '/api/business-finder/categories', summary: 'Business categories', tags: ['Business Finder'], security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'value', type: 'string', example: 'Hospitals'),
                    new OA\Property(property: 'search_term', type: 'string', example: 'Hospitals'),
                    new OA\Property(property: 'assignable', type: 'boolean', description: 'false for "All businesses" (search filter only)'),
                    new OA\Property(property: 'needs_keywords', type: 'boolean', description: 'true for "Other businesses"'),
                ])),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function categories(): JsonResponse
    {
        return response()->json(['data' => array_map(fn (BusinessCategory $c) => [
            'value' => $c->value,
            'search_term' => $c->searchTerm(),
            'assignable' => $c !== BusinessCategory::All,
            'needs_keywords' => $c === BusinessCategory::Other,
        ], BusinessCategory::cases())]);
    }

    #[OA\Get(
        path: '/api/business-finder/locations', summary: 'Tamil Nadu districts and their main towns', tags: ['Business Finder'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'district', in: 'query', description: 'Only this district', schema: new OA\Schema(type: 'string', example: 'Madurai'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'state', type: 'string', example: 'Tamil Nadu'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'district', type: 'string', example: 'Madurai'),
                    new OA\Property(property: 'cities', type: 'array', items: new OA\Items(type: 'string'), example: ['Madurai', 'Melur']),
                ])),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function locations(Request $request): JsonResponse
    {
        $districts = collect(config('business_finder.districts'))
            ->when($request->filled('district'), fn ($c) => $c->filter(fn ($_, $d) => strcasecmp($d, $request->district) === 0))
            ->map(fn ($cities, $district) => ['district' => $district, 'cities' => $cities])
            ->values();

        return response()->json(['state' => config('business_finder.state'), 'data' => $districts]);
    }

    #[OA\Get(
        path: '/api/business-finder/search-links', summary: 'Generate free Google Maps / Google Search links for manual research',
        description: "Builds links locally (no Google API, no scraping) and saves the search to history.\n\nExample: `category=Hospitals&city=Chennai` → query **Hospitals in Chennai**, Maps link `https://www.google.com/maps/search/?api=1&query=Hospitals%20in%20Chennai`.",
        tags: ['Business Finder'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'business_name', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'match_type', in: 'query', description: 'exact = quoted name', schema: new OA\Schema(type: 'string', default: 'partial', enum: ['exact', 'partial'])),
            new OA\Parameter(name: 'keywords', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', example: 'Hospitals')),
            new OA\Parameter(name: 'district', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'city', in: 'query', schema: new OA\Schema(type: 'string', example: 'Chennai')),
            new OA\Parameter(name: 'area', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'pincode', in: 'query', schema: new OA\Schema(type: 'string', example: '600040')),
            new OA\Parameter(name: 'quantity', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, enum: [20, 50, 100])),
            new OA\Parameter(name: 'website_filter', in: 'query', description: 'Remembered with the search; applied to the saved-business list', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'contact_filter', in: 'query', schema: new OA\Schema(type: 'string', enum: ['phone_available', 'phone_missing', 'pending', 'verified'])),
            new OA\Parameter(name: 'save_history', in: 'query', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/SearchLinks'),
                new OA\Property(property: 'search_id', type: 'integer', nullable: true, description: 'Send as business_search_id when adding a business from this search'),
                new OA\Property(property: 'already_saved', type: 'integer', description: 'Your saved businesses matching this category/location'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function searchLinks(SearchLinksRequest $request, SearchLinkBuilder $builder): JsonResponse
    {
        $params = $request->params();
        $links = $builder->build($params);

        $search = null;
        if (! $request->has('save_history') || $request->boolean('save_history')) {
            $search = $request->user()->businessSearches()->create([...$params, 'query' => Str::limit($links['query'], 295, '')]);
        }

        $alreadySaved = BusinessLeadQuery::build($request->user(), [
            'category' => $params['category'], 'city' => $params['city'], 'district' => $params['city'] ? null : $params['district'],
        ])->count();

        return response()->json(['data' => $links, 'search_id' => $search?->id, 'already_saved' => $alreadySaved]);
    }

    #[OA\Get(
        path: '/api/business-finder/history', summary: 'Search history (newest first)', tags: ['Business Finder'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Filter by query text', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/per_page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated history', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/BusinessSearch')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function history(Request $request): AnonymousResourceCollection
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $query = $request->user()->businessSearches()->withCount('businessLeads')
            ->when($request->filled('search'), fn ($q) => $q->where('query', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $request->search).'%'))
            ->latest()->orderByDesc('id');

        return BusinessSearchResource::collection($query->paginate($this->perPage())->withQueryString());
    }

    #[OA\Delete(
        path: '/api/business-finder/history/{id}', summary: 'Remove a search from history', tags: ['Business Finder'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Search removed from history.')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroyHistory(Request $request, int $id): JsonResponse
    {
        $request->user()->businessSearches()->findOrFail($id)->delete();

        return response()->json(['message' => 'Search removed from history.']);
    }

    #[OA\Get(
        path: '/api/business-finder/suggestions', summary: 'What to research or verify next',
        description: 'Suggested searches (categories not yet searched in your usual cities), recent searches, businesses waiting for verification and businesses not yet saved to the CRM.',
        tags: ['Business Finder'], security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'suggested_searches', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'category', type: 'string'),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'reason', type: 'string'),
                    new OA\Property(property: 'links', ref: '#/components/schemas/SearchLinks'),
                ])),
                new OA\Property(property: 'recent_searches', type: 'array', items: new OA\Items(ref: '#/components/schemas/BusinessSearch')),
                new OA\Property(property: 'needs_verification', type: 'array', items: new OA\Items(ref: '#/components/schemas/BusinessLead')),
                new OA\Property(property: 'not_saved_to_crm', type: 'array', items: new OA\Items(ref: '#/components/schemas/BusinessLead')),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function suggestions(Request $request, SearchLinkBuilder $builder): JsonResponse
    {
        $user = $request->user();

        $cities = $user->businessSearches()->whereNotNull('city')->selectRaw('city, COUNT(*) as n')
            ->groupBy('city')->orderByDesc('n')->limit(3)->pluck('city');
        if ($cities->isEmpty()) {
            $cities = $user->businessLeads()->whereNotNull('city')->selectRaw('city, COUNT(*) as n')
                ->groupBy('city')->orderByDesc('n')->limit(3)->pluck('city');
        }
        $usual = $cities->isNotEmpty();
        if (! $usual) {
            $cities = collect(['Chennai', 'Coimbatore', 'Madurai']);
        }

        $recentPairs = $user->businessSearches()->where('created_at', '>=', now()->subDays(30))
            ->get(['category', 'city'])->map(fn ($s) => strtolower(($s->category?->value ?? '').'|'.$s->city))->flip();

        $suggested = [];
        foreach ($cities as $city) {
            foreach (BusinessCategory::cases() as $cat) {
                if (in_array($cat, [BusinessCategory::All, BusinessCategory::Other], true) || isset($recentPairs[strtolower("{$cat->value}|$city")])) {
                    continue;
                }
                $suggested[] = [
                    'category' => $cat->value,
                    'city' => $city,
                    'reason' => $usual ? "Not searched in $city in the last 30 days" : 'Popular starting point',
                    'links' => $builder->build(['category' => $cat->value, 'city' => $city]),
                ];
                if (count($suggested) >= 8) {
                    break 2;
                }
            }
        }

        return response()->json([
            'suggested_searches' => $suggested,
            'recent_searches' => BusinessSearchResource::collection($user->businessSearches()->withCount('businessLeads')->latest()->orderByDesc('id')->limit(5)->get()),
            'needs_verification' => BusinessLeadResource::collection($user->businessLeads()->with('lead')->contactable()
                ->where(fn ($q) => $q->where('website_status', WebsiteStatus::Pending)->orWhere('contact_status', ContactStatus::Pending))
                ->latest()->limit(5)->get()),
            'not_saved_to_crm' => BusinessLeadResource::collection($user->businessLeads()->with('lead')->notSaved()->contactable()
                ->whereNotNull('phone_digits')->latest()->limit(5)->get()),
        ]);
    }

    #[OA\Get(
        path: '/api/business-finder/dashboard', summary: 'Business Finder dashboard: statistics and discovery summary', tags: ['Business Finder'], security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'stats', ref: '#/components/schemas/BusinessFinderStats'),
                new OA\Property(property: 'summary', type: 'string', example: '42 businesses researched, 30 saved to CRM. 18 have no website, 20 contacts verified, 9 pending verification. 6 interested, 2 converted.'),
                new OA\Property(property: 'by_category', type: 'object', example: ['Hospitals' => 12, 'Schools' => 8]),
                new OA\Property(property: 'by_city', type: 'object', example: ['Chennai' => 20, 'Madurai' => 9]),
                new OA\Property(property: 'recent_businesses', type: 'array', items: new OA\Items(ref: '#/components/schemas/BusinessLead')),
                new OA\Property(property: 'recent_searches', type: 'array', items: new OA\Items(ref: '#/components/schemas/BusinessSearch')),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function dashboard(Request $request, BusinessFinderStats $stats): JsonResponse
    {
        $user = $request->user();
        $s = $stats->stats($user);

        return response()->json([
            'stats' => $s,
            'summary' => $stats->summary($s),
            'by_category' => $user->businessLeads()->selectRaw('category, COUNT(*) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category'),
            'by_city' => $user->businessLeads()->whereNotNull('city')->selectRaw('city, COUNT(*) as total')->groupBy('city')->orderByDesc('total')->limit(10)->pluck('total', 'city'),
            'recent_businesses' => BusinessLeadResource::collection($user->businessLeads()->with('lead')->latest()->orderByDesc('id')->limit(5)->get()),
            'recent_searches' => BusinessSearchResource::collection($user->businessSearches()->withCount('businessLeads')->latest()->orderByDesc('id')->limit(5)->get()),
        ]);
    }

    #[OA\Get(
        path: '/api/business-finder/check-duplicate', summary: 'Check a phone number / business name before saving',
        tags: ['Business Finder'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'phone', in: 'query', schema: new OA\Schema(type: 'string', example: '+91 98765 43210')),
            new OA\Parameter(name: 'business_name', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'city', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'is_duplicate', type: 'boolean', description: 'Phone already used by a CRM lead or a researched business'),
                new OA\Property(property: 'lead', ref: '#/components/schemas/Lead', nullable: true),
                new OA\Property(property: 'business_lead', ref: '#/components/schemas/BusinessLead', nullable: true),
                new OA\Property(property: 'similar', type: 'array', description: 'Same business name in the same city (warning only)', items: new OA\Items(ref: '#/components/schemas/BusinessLead')),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function checkDuplicate(Request $request, BusinessLeadService $service): JsonResponse
    {
        $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);
        $dup = $service->findDuplicates($request->user(), $request->phone, $request->business_name, $request->city);

        return response()->json([
            'is_duplicate' => $dup['lead'] !== null || $dup['business_lead'] !== null,
            'lead' => $dup['lead'] ? new \App\Http\Resources\LeadResource($dup['lead']) : null,
            'business_lead' => $dup['business_lead'] ? new BusinessLeadResource($dup['business_lead']) : null,
            'similar' => BusinessLeadResource::collection($dup['similar']),
        ]);
    }

    #[OA\Post(
        path: '/api/business-finder/verify-website', summary: 'Check whether a website responds (not saved)',
        description: "Optional, polite check: validates the URL, refuses localhost/private addresses, reads robots.txt first, follows up to 5 redirects, handles SSL errors and uses short timeouts. Limited to 10 checks per minute.\n\n**A failed check never means the website does not exist.**",
        tags: ['Business Finder'], security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['url'], properties: [
            new OA\Property(property: 'url', type: 'string', example: 'example.com'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Check result', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/WebsiteCheck')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, description: 'Too many checks'),
        ]
    )]
    public function verifyWebsite(VerifyWebsiteRequest $request, WebsiteChecker $checker): JsonResponse
    {
        $result = $checker->check($request->input('url'));
        AuditLog::record('website.checked', null, ['url' => $result['url'] ?? $request->input('url'), 'result' => $result['result']]);

        return response()->json(['data' => $result]);
    }
}
