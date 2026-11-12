<?php

namespace App\Services;

use App\Enums\FollowupStatus;
use App\Enums\LeadStatus;
use App\Models\AuditLog;
use App\Models\BusinessLead;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Per-salesperson performance for admins (everyone) and managers (their team). */
class TeamReportService
{
    /**
     * @return array{rows: Collection<int, array>, totals: array, from: Carbon, to: Carbon}
     */
    public function report(User $viewer, Carbon $from, Carbon $to, ?int $onlyUserId = null): array
    {
        $users = $viewer->visibleUsers()
            ->when($onlyUserId, fn ($q) => $q->whereKey($onlyUserId))
            ->get(['id', 'name', 'email', 'role', 'is_active']);
        $ids = $users->pluck('id');
        $range = [$from, $to];

        $count = fn ($query, string $col = 'user_id') => $query->whereIn($col, $ids)
            ->selectRaw("$col as uid, COUNT(*) as n")->groupBy($col)->pluck('n', 'uid');

        $leadsAdded = $count(Lead::query()->whereBetween('created_at', $range));
        $leadsTotal = $count(Lead::query());
        $byStatus = Lead::query()->whereIn('user_id', $ids)
            ->selectRaw('user_id, status, COUNT(*) as n')->groupBy('user_id', 'status')->toBase()->get()
            ->groupBy('user_id')->map(fn ($g) => $g->pluck('n', 'status'));
        $calls = $count(Followup::query()->where('status', FollowupStatus::Completed)->whereBetween('completed_at', $range));
        $pending = $count(Followup::query()->activeLead()->pending());
        $overdue = $count(Followup::query()->activeLead()->overdue());
        $researched = $count(BusinessLead::query()->whereBetween('created_at', $range));
        $saved = $count(BusinessLead::query()->whereNotNull('lead_id')->whereBetween('created_at', $range));

        $rows = $users->map(function (User $u) use ($leadsAdded, $leadsTotal, $byStatus, $calls, $pending, $overdue, $researched, $saved) {
            $status = $byStatus[$u->id] ?? collect();
            $total = (int) ($leadsTotal[$u->id] ?? 0);
            $won = (int) ($status[LeadStatus::Won->value] ?? 0);

            return [
                'user' => $u,
                'leads_added' => (int) ($leadsAdded[$u->id] ?? 0),
                'leads_total' => $total,
                'calls' => (int) ($calls[$u->id] ?? 0),
                'pending' => (int) ($pending[$u->id] ?? 0),
                'overdue' => (int) ($overdue[$u->id] ?? 0),
                'interested' => (int) ($status[LeadStatus::Interested->value] ?? 0),
                'won' => $won,
                'lost' => (int) ($status[LeadStatus::Lost->value] ?? 0),
                'conversion' => $total ? round($won * 100 / $total, 1) : 0.0,
                'researched' => (int) ($researched[$u->id] ?? 0),
                'saved_to_crm' => (int) ($saved[$u->id] ?? 0),
            ];
        })->sortByDesc('calls')->values();

        $sum = fn (string $k) => $rows->sum($k);
        $totals = [
            'leads_added' => $sum('leads_added'), 'leads_total' => $sum('leads_total'), 'calls' => $sum('calls'),
            'pending' => $sum('pending'), 'overdue' => $sum('overdue'), 'interested' => $sum('interested'),
            'won' => $sum('won'), 'lost' => $sum('lost'), 'researched' => $sum('researched'), 'saved_to_crm' => $sum('saved_to_crm'),
        ];
        $totals['conversion'] = $totals['leads_total'] ? round($totals['won'] * 100 / $totals['leads_total'], 1) : 0.0;

        return ['rows' => $rows, 'totals' => $totals, 'from' => $from, 'to' => $to];
    }

    /** Latest audit-log entries of the users the viewer can see. */
    public function activity(User $viewer, int $limit = 30): Collection
    {
        $ids = $viewer->visibleUserIds();

        return AuditLog::with('user:id,name')
            ->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids))
            ->latest('id')->limit($limit)->get();
    }

    /** Rows for the Excel/CSV export. */
    public static function headers(): array
    {
        return ['Salesperson', 'Email', 'Role', 'Leads added', 'Total leads', 'Calls done', 'Pending follow-ups',
            'Overdue', 'Interested', 'Won', 'Lost', 'Conversion %', 'Businesses researched', 'Saved to CRM'];
    }

    public static function row(array $r): array
    {
        return [$r['user']->name, $r['user']->email, $r['user']->role->label(), $r['leads_added'], $r['leads_total'],
            $r['calls'], $r['pending'], $r['overdue'], $r['interested'], $r['won'], $r['lost'], $r['conversion'],
            $r['researched'], $r['saved_to_crm']];
    }
}
