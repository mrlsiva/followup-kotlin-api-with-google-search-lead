<?php

namespace App\Http\Controllers\Web;

use App\Enums\LeadStatus;
use App\Services\BusinessFinder\BusinessFinderStats;
use App\Services\TeamReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends WebController
{
    public function __invoke(Request $request, BusinessFinderStats $finder, TeamReportService $reports): View
    {
        $user = $request->user();
        $followups = fn () => $user->followups()->activeLead();
        $byStatus = $user->leads()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $team = null;
        if ($user->canViewTeam()) {
            $team = $reports->report($user, now()->startOfMonth(), now()->endOfDay());
        }

        return view('dashboard', [
            'stats' => [
                'total_leads' => $user->leads()->count(),
                'today' => $followups()->today()->count(),
                'overdue' => $followups()->overdue()->count(),
                'pending' => $followups()->pending()->count(),
                'completed' => $followups()->completed()->count(),
            ],
            'byStatus' => collect(LeadStatus::values())->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)]),
            'today' => $followups()->today()->with('lead')->orderBy('scheduled_at')->get(),
            'overdue' => $followups()->overdue()->with('lead')->orderBy('scheduled_at')->limit(10)->get(),
            'recentLeads' => $user->leads()->latest()->limit(5)->get(),
            'finder' => $finder->stats($user),
            'team' => $team,
        ]);
    }
}
