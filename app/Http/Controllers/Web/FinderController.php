<?php

namespace App\Http\Controllers\Web;

use App\Support\Categories;
use App\Enums\ContactStatus;
use App\Enums\WebsiteStatus;
use App\Http\Requests\BusinessFinder\SearchLinksRequest;
use App\Http\Requests\BusinessFinder\VerifyWebsiteRequest;
use App\Models\AuditLog;
use App\Services\BusinessFinder\BusinessFinderStats;
use App\Services\BusinessFinder\BusinessLeadQuery;
use App\Services\BusinessFinder\BusinessLeadService;
use App\Services\BusinessFinder\SearchLinkBuilder;
use App\Services\BusinessFinder\WebsiteChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Business Lead Finder: dashboard, search links, history, suggestions, AJAX helpers. */
class FinderController extends WebController
{
    public function dashboard(Request $request, BusinessFinderStats $stats, SearchLinkBuilder $builder): View
    {
        $user = $request->user();
        $s = $stats->stats($user);

        return view('finder.dashboard', [
            'stats' => $s,
            'summary' => $stats->summary($s),
            'byCategory' => $user->businessLeads()->selectRaw('category, COUNT(*) as total')->groupBy('category')->orderByDesc('total')->limit(8)->pluck('total', 'category'),
            'recent' => $user->businessLeads()->with('lead')->latest()->orderByDesc('id')->limit(6)->get(),
            'needsVerification' => $user->businessLeads()->contactable()
                ->where(fn ($q) => $q->where('website_status', WebsiteStatus::Pending)->orWhere('contact_status', ContactStatus::Pending))
                ->latest()->limit(6)->get(),
            'suggestions' => $this->suggestions($request, $builder),
        ]);
    }

    public function search(): View
    {
        return view('finder.search', ['links' => null, 'search' => null, 'params' => []]);
    }

    public function results(SearchLinksRequest $request, SearchLinkBuilder $builder): View
    {
        $params = $request->params();
        $links = $builder->build($params);
        $search = $request->user()->businessSearches()->create([...$params, 'query' => Str::limit($links['query'], 295, '')]);

        $already = BusinessLeadQuery::build($request->user(), [
            'category' => $params['category'], 'city' => $params['city'], 'district' => $params['city'] ? null : $params['district'],
        ])->with('lead')->limit(10)->get();

        return view('finder.search', ['links' => $links, 'search' => $search, 'params' => $params, 'already' => $already]);
    }

    public function history(Request $request): View
    {
        $searches = $request->user()->businessSearches()->withCount('businessLeads')
            ->when($request->filled('search'), fn ($q) => $q->where('query', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $request->search).'%'))
            ->latest()->orderByDesc('id')->paginate(20)->withQueryString();

        return view('finder.history', ['searches' => $searches]);
    }

    public function destroyHistory(Request $request, int $id): RedirectResponse
    {
        $request->user()->businessSearches()->findOrFail($id)->delete();

        return back()->with('success', __('Search removed from history.'));
    }

    /** AJAX: duplicate check while typing the phone number. */
    public function checkDuplicate(Request $request, BusinessLeadService $service): JsonResponse
    {
        $request->validate(['phone' => ['nullable', 'string', 'max:20'], 'business_name' => ['nullable', 'string', 'max:150'], 'city' => ['nullable', 'string', 'max:80'], 'except' => ['nullable', 'integer']]);
        $dup = $service->findDuplicates($request->user(), $request->phone, $request->business_name, $request->city, $request->integer('except') ?: null);
        $lead = $dup['lead'];
        $business = $dup['business_lead'];
        if ($business && $request->integer('except') && $lead && $business->lead_id === $lead->id) {
            $lead = null;
        }

        return response()->json([
            'is_duplicate' => (bool) ($lead || $business),
            'lead' => $lead ? ['id' => $lead->id, 'name' => $lead->name, 'company' => $lead->company, 'url' => route('leads.show', $lead)] : null,
            'business' => $business ? ['id' => $business->id, 'name' => $business->business_name, 'url' => route('businesses.show', $business)] : null,
            'similar' => $dup['similar']->map(fn ($b) => ['id' => $b->id, 'name' => $b->business_name, 'city' => $b->city, 'url' => route('businesses.show', $b)]),
        ]);
    }

    /** AJAX: ad-hoc website check (not saved). */
    public function verifyWebsite(VerifyWebsiteRequest $request, WebsiteChecker $checker): JsonResponse
    {
        $result = $checker->check($request->input('url'));
        AuditLog::record('website.checked', null, ['url' => $result['url'] ?? $request->input('url'), 'result' => $result['result']]);

        return response()->json(['data' => $result]);
    }

    /** Categories not searched in the user's usual cities in the last 30 days. */
    private function suggestions(Request $request, SearchLinkBuilder $builder): array
    {
        $user = $request->user();
        $cities = $user->businessSearches()->whereNotNull('city')->selectRaw('city, COUNT(*) as n')->groupBy('city')->orderByDesc('n')->limit(3)->pluck('city');
        if ($cities->isEmpty()) {
            $cities = collect(['Chennai', 'Coimbatore', 'Madurai']);
        }
        $recent = $user->businessSearches()->where('created_at', '>=', now()->subDays(30))->get(['category', 'city'])
            ->map(fn ($s) => strtolower(($s->category ?? '').'|'.$s->city))->flip();

        $out = [];
        foreach ($cities as $city) {
            foreach (Categories::active() as $cat) {
                if ($cat->isSystem() || isset($recent[strtolower("{$cat->name}|$city")])) {
                    continue;
                }
                $out[] = ['category' => $cat->name, 'city' => $city, 'links' => $builder->build(['category' => $cat->name, 'city' => $city])];
                if (count($out) >= 6) {
                    break 2;
                }
            }
        }

        return $out;
    }
}
