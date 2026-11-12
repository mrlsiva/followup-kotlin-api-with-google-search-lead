<?php

namespace App\Http\Controllers\Web;

use App\Support\Categories;
use App\Enums\ContactStatus;
use App\Enums\WebsiteStatus;
use App\Exceptions\DuplicateBusinessException;
use App\Http\Requests\BusinessFinder\BusinessLeadRequest;
use App\Http\Requests\BusinessFinder\ConvertBusinessLeadRequest;
use App\Http\Requests\BusinessFinder\VerifyContactRequest;
use App\Http\Requests\BusinessFinder\VerifyWebsiteRequest;
use App\Models\AuditLog;
use App\Models\BusinessLead;
use App\Services\BusinessFinder\BusinessLeadExporter;
use App\Services\BusinessFinder\BusinessLeadQuery;
use App\Services\BusinessFinder\BusinessLeadService;
use App\Services\BusinessFinder\WebsiteChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Researched businesses: list, add to CRM, edit, verify, convert, export. */
class BusinessController extends WebController
{
    public function __construct(private readonly BusinessLeadService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(BusinessLeadQuery::rules());
        $base = $this->ownerScope($request, BusinessLead::query());

        return view('businesses.index', [
            'businesses' => BusinessLeadQuery::build($request->user(), $filters, $base)->with(['lead', 'user:id,name'])
                ->paginate(20)->withQueryString(),
            'users' => $this->teamUsers($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        $search = $request->filled('search_id') ? $request->user()->businessSearches()->find($request->integer('search_id')) : null;

        return view('businesses.form', [
            'business' => new BusinessLead([
                'category' => $search?->category && in_array($search->category, Categories::assignable(), true) ? $search->category : Categories::OTHER,
                'city' => $search?->city,
                'district' => $search?->district,
                'area' => $search?->area,
                'pincode' => $search?->pincode,
                'website_status' => WebsiteStatus::Pending,
                'contact_status' => ContactStatus::Pending,
            ]),
            'search' => $search,
        ]);
    }

    public function store(BusinessLeadRequest $request): RedirectResponse
    {
        try {
            $business = $this->service->create($request->user(), $request->businessData(), $request->crmOptions());
        } catch (DuplicateBusinessException $e) {
            return back()->withInput()->with('duplicate', [
                'message' => $e->getMessage(),
                'lead' => $e->lead ? ['name' => $e->lead->name, 'company' => $e->lead->company, 'url' => route('leads.show', $e->lead)] : null,
                'business' => $e->businessLead ? ['name' => $e->businessLead->business_name, 'url' => route('businesses.show', $e->businessLead)] : null,
                'can_link' => $e->businessLead === null,
            ]);
        }

        return redirect()->route('businesses.show', $business)
            ->with('success', $business->lead ? __('Business saved to CRM as a new lead.') : __('Business saved.'));
    }

    public function show(Request $request, BusinessLead $businessLead): View
    {
        $businessLead->load(['user:id,name', 'search', 'lead.followups' => fn ($q) => $q
            ->orderByRaw("status = 'pending' DESC")
            ->orderByRaw('COALESCE(completed_at, scheduled_at) DESC')]);

        return view('businesses.show', [
            'b' => $businessLead,
            'isOwner' => $businessLead->user_id === $request->user()->id,
        ]);
    }

    public function edit(BusinessLead $businessLead): View
    {
        $this->ensureOwner($businessLead);

        return view('businesses.form', ['business' => $businessLead, 'search' => null]);
    }

    public function update(BusinessLeadRequest $request, BusinessLead $businessLead): RedirectResponse
    {
        $this->ensureOwner($businessLead);
        try {
            $this->service->update($businessLead, $request->businessData());
        } catch (DuplicateBusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('businesses.show', $businessLead)->with('success', __('Business updated.'));
    }

    public function destroy(BusinessLead $businessLead): RedirectResponse
    {
        $this->ensureOwner($businessLead);
        $businessLead->delete();
        AuditLog::record('business_lead.deleted', $businessLead, ['business_name' => $businessLead->business_name]);

        return redirect()->route('businesses.index')->with('success', __('Business removed. The CRM lead (if any) is kept.'));
    }

    public function convert(ConvertBusinessLeadRequest $request, BusinessLead $businessLead): RedirectResponse
    {
        $this->ensureOwner($businessLead);
        if ($businessLead->isSavedToCrm()) {
            return back()->with('error', __('This business is already saved to the CRM.'));
        }
        try {
            $this->service->convert($businessLead, $request->options());
        } catch (DuplicateBusinessException $e) {
            return back()->with('duplicate', [
                'message' => $e->getMessage(),
                'lead' => $e->lead ? ['name' => $e->lead->name, 'company' => $e->lead->company, 'url' => route('leads.show', $e->lead)] : null,
                'business' => null,
                'can_link' => true,
            ]);
        }

        return back()->with('success', __('Business saved to CRM as a new lead.'));
    }

    public function verifyContact(VerifyContactRequest $request, BusinessLead $businessLead): RedirectResponse
    {
        $this->ensureOwner($businessLead);
        try {
            $this->service->verifyContact($businessLead, $request->validated());
        } catch (DuplicateBusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Contact verification saved.'));
    }

    /** AJAX: check and store the website result. */
    public function verifyWebsite(VerifyWebsiteRequest $request, BusinessLead $businessLead, WebsiteChecker $checker): JsonResponse
    {
        $this->ensureOwner($businessLead);
        $result = $checker->check($request->input('url') ?: $businessLead->website_url);
        if (in_array($result['result'], [WebsiteChecker::INVALID_URL, WebsiteChecker::NOT_ALLOWED], true)) {
            return response()->json(['message' => $result['message'], 'check' => $result], 422);
        }
        $b = $this->service->applyWebsiteCheck($businessLead, $result, $request->filled('url') ? $result['url'] : null);

        return response()->json([
            'check' => $result,
            'business' => [
                'website_url' => $b->website_url,
                'website_status' => $b->website_status->value,
                'website_status_label' => __($b->website_status->value),
                'website_checked_at' => $b->website_checked_at?->format('d M Y, h:i A'),
                'website_http_status' => $b->website_http_status,
            ],
        ]);
    }

    public function export(Request $request, string $format, BusinessLeadExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        $spec = ['csv' => ['csv', 'text/csv'], 'excel' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'pdf' => ['pdf', 'application/pdf']][$format];
        $filters = $request->validate(BusinessLeadQuery::rules());
        $base = $this->ownerScope($request, BusinessLead::query());
        try {
            $result = $exporter->export($request->user(), $filters, $spec[0], $base);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditLog::record('business_lead.exported', null, ['format' => $format, 'count' => $result['count'], 'filters' => $filters]);

        return response()->download($result['path'], 'business_leads_'.now()->format('Ymd_His').'.'.$spec[0], ['Content-Type' => $spec[1]])->deleteFileAfterSend();
    }
}
