<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\ImportLeadsRequest;
use App\Models\AuditLog;
use App\Services\LeadTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Excel / CSV import and export of the user's own CRM leads. */
class TransferController extends WebController
{
    public function __construct(private readonly LeadTransferService $transfer) {}

    public function index(): View
    {
        return view('transfer.index', ['headers' => LeadTransferService::HEADERS]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $format = $request->input('format') === 'csv' ? 'csv' : 'xlsx';
        $path = $this->transfer->export($request->user(), $format);
        AuditLog::record('lead.exported', null, ['format' => $format]);
        $mime = $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv';

        return response()->download($path, 'leads_'.now()->format('Ymd_His').'.'.$format, ['Content-Type' => $mime])->deleteFileAfterSend();
    }

    public function import(ImportLeadsRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        try {
            $result = $this->transfer->import($request->user(), $file->getRealPath(), $ext);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        AuditLog::record('lead.imported', null, array_diff_key($result, ['errors' => 1]));

        return back()->with('import_result', $result)->with('success', __('Import complete.'));
    }
}
