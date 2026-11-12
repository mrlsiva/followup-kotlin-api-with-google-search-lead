<?php

namespace App\Http\Controllers\Web;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\FollowupNoteRequest;
use App\Http\Requests\LeadRequest;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\User;
use App\Services\FollowupService;
use App\Services\LeadOwnershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class LeadController extends WebController
{
    public function __construct(private readonly FollowupService $followups) {}

    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'sort' => ['nullable', 'in:latest,oldest,name,next_followup,priority'],
        ]);

        $query = $this->ownerScope($request, Lead::query())
            ->with('user:id,name')
            ->search($request->input('search'))
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

        return view('leads.index', [
            'leads' => $query->paginate(20)->withQueryString(),
            'users' => $this->teamUsers($request->user()),
        ]);
    }

    public function create(): View
    {
        return view('leads.form', ['lead' => new Lead(['source' => LeadSource::Call, 'status' => LeadStatus::New, 'priority' => LeadPriority::Medium])]);
    }

    public function store(LeadRequest $request): RedirectResponse
    {
        $lead = DB::transaction(function () use ($request) {
            $lead = $request->user()->leads()->create($request->leadData());
            $this->followups->syncNextFollowup($lead, $request->nextFollowupAt());

            return $lead;
        });
        AuditLog::record('lead.created', $lead, ['name' => $lead->name]);

        return redirect()->route('leads.show', $lead)->with('success', __('Lead added.'));
    }

    public function show(Request $request, Lead $lead): View
    {
        $lead->load(['user:id,name', 'businessLead', 'followups' => fn ($q) => $q
            ->orderByRaw("status = 'pending' DESC")
            ->orderByRaw('COALESCE(completed_at, scheduled_at) DESC')]);

        $user = $request->user();
        $canReassign = $user->canReassign($lead->user_id);

        return view('leads.show', [
            'lead' => $lead,
            'isOwner' => $lead->user_id === $user->id,
            'canReassign' => $canReassign,
            'assignees' => $canReassign ? $user->visibleUsers()->active()->get(['id', 'name']) : collect(),
        ]);
    }

    public function edit(Lead $lead): View
    {
        $this->ensureOwner($lead);

        return view('leads.form', ['lead' => $lead]);
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->ensureOwner($lead);
        DB::transaction(function () use ($request, $lead) {
            $lead->update($request->leadData());
            if ($request->hasNextFollowupInput()) {
                $this->followups->syncNextFollowup($lead, $request->nextFollowupAt());
            }
        });
        AuditLog::record('lead.updated', $lead);

        return redirect()->route('leads.show', $lead)->with('success', __('Lead updated.'));
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->ensureOwner($lead);
        DB::transaction(function () use ($lead) {
            $lead->followups()->pending()->delete();
            $lead->forceFill(['next_followup_at' => null])->save();
            $lead->delete();
        });
        AuditLog::record('lead.deleted', $lead, ['name' => $lead->name]);

        return redirect()->route('leads.index')->with('success', __('Lead deleted.'));
    }

    /** Add a call note (and optionally schedule the next follow-up). */
    public function addNote(FollowupNoteRequest $request, Lead $lead): RedirectResponse
    {
        $this->ensureOwner($lead);
        $this->followups->addNote($lead, $request->serviceData());

        return redirect()->route('leads.show', $lead)->with('success', __('Note added.'));
    }

    public function reassign(Request $request, Lead $lead, LeadOwnershipService $ownership): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canReassign($lead->user_id), 403);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $to = User::findOrFail($data['user_id']);
        abort_unless($user->canReassign($to->id) && $to->is_active, 403);

        try {
            $ownership->reassign($lead, $to);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('leads.show', $lead)->with('success', __('Lead assigned to :name.', ['name' => $to->name]));
    }
}
