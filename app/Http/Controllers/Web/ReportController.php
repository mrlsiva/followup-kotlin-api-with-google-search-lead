<?php

namespace App\Http\Controllers\Web;

use App\Models\AuditLog;
use App\Services\LeadTransferService;
use App\Services\TeamReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Team reports (admins: everyone, managers: their team). */
class ReportController extends WebController
{
    public function __construct(private readonly TeamReportService $reports) {}

    public function index(Request $request): View
    {
        [$from, $to, $period] = $this->range($request);
        $user = $request->user();

        return view('reports.index', [
            ...$this->reports->report($user, $from, $to, $this->userFilter($request)),
            'period' => $period,
            'users' => $this->teamUsers($user),
            'activity' => $this->reports->activity($user),
        ]);
    }

    public function export(Request $request, string $format, LeadTransferService $writer): BinaryFileResponse|Response
    {
        [$from, $to] = $this->range($request);
        $report = $this->reports->report($request->user(), $from, $to, $this->userFilter($request));
        $name = 'team_report_'.$from->format('Ymd').'_'.$to->format('Ymd');
        AuditLog::record('report.exported', null, ['format' => $format, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);

        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', [...$report, 'viewer' => $request->user()])
                ->setPaper('a4', 'landscape')->setOption('enable_font_subsetting', true)->download("$name.pdf");
        }
        $rows = $report['rows']->map(fn ($r) => TeamReportService::row($r));
        $path = $writer->writeSheet('xlsx', TeamReportService::headers(), $rows, 'team_report_');

        return response()->download($path, "$name.xlsx")->deleteFileAfterSend();
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function range(Request $request): array
    {
        $request->validate([
            'period' => ['nullable', 'in:today,week,month,last_month,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $period = $request->input('period', 'month');

        return match ($period) {
            'today' => [today(), today()->endOfDay(), $period],
            'week' => [now()->startOfWeek(), now()->endOfDay(), $period],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), $period],
            'custom' => [Carbon::parse($request->input('from', today()->toDateString()))->startOfDay(),
                Carbon::parse($request->input('to', today()->toDateString()))->endOfDay(), $period],
            default => [now()->startOfMonth(), now()->endOfDay(), 'month'],
        };
    }

    private function userFilter(Request $request): ?int
    {
        $id = $request->integer('user') ?: null;

        return $id && $request->user()->canSeeUser($id) ? $id : null;
    }
}
