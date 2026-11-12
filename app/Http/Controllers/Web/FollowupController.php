<?php

namespace App\Http\Controllers\Web;

use App\Enums\FollowupStatus;
use App\Http\Requests\FollowupNoteRequest;
use App\Http\Requests\RescheduleFollowupRequest;
use App\Models\Followup;
use App\Services\FollowupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowupController extends WebController
{
    public const TABS = ['today', 'overdue', 'upcoming', 'pending', 'completed'];

    public function __construct(private readonly FollowupService $followups) {}

    public function index(Request $request): View
    {
        $type = in_array($request->input('type'), self::TABS, true) ? $request->input('type') : 'today';
        $base = fn () => $request->user()->followups()->activeLead();

        $query = $base()->with('lead')
            ->when($request->filled('search'), fn ($q) => $q->whereHas('lead', fn ($l) => $l->search($request->search)));
        match ($type) {
            'overdue' => $query->overdue()->orderBy('scheduled_at'),
            'upcoming' => $query->upcoming()->orderBy('scheduled_at'),
            'pending' => $query->pending()->orderBy('scheduled_at'),
            'completed' => $query->completed()->latest('completed_at'),
            default => $query->today()->orderBy('scheduled_at'),
        };

        return view('followups.index', [
            'type' => $type,
            'followups' => $query->paginate(25)->withQueryString(),
            'counts' => [
                'today' => $base()->today()->count(),
                'overdue' => $base()->overdue()->count(),
                'upcoming' => $base()->upcoming()->count(),
                'pending' => $base()->pending()->count(),
            ],
        ]);
    }

    public function complete(FollowupNoteRequest $request, Followup $followup): RedirectResponse
    {
        $this->ensureOwner($followup);
        if (! $followup->isPending()) {
            return back()->with('error', __('This follow-up is already completed.'));
        }
        $done = $this->followups->complete($followup, $request->serviceData());
        $next = $done->lead->refresh()->next_followup_at;

        return back()->with('success', $next
            ? __('Follow-up completed. Next call: :when', ['when' => $next->format('d M Y, h:i A')])
            : __('Follow-up completed.'));
    }

    public function reschedule(RescheduleFollowupRequest $request, Followup $followup): RedirectResponse
    {
        $this->ensureOwner($followup);
        if (! $followup->isPending()) {
            return back()->with('error', __('Completed follow-ups cannot be rescheduled.'));
        }
        $this->followups->syncNextFollowup($followup->lead, $request->nextFollowupAt());

        return back()->with('success', __('Follow-up rescheduled.'));
    }

    public function destroy(Followup $followup): RedirectResponse
    {
        $this->ensureOwner($followup);
        if ($followup->status === FollowupStatus::Pending) {
            $this->followups->syncNextFollowup($followup->lead, null);
        } else {
            $followup->delete();
        }

        return back()->with('success', __('Follow-up deleted.'));
    }
}
