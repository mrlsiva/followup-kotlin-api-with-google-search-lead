<?php

namespace App\Services\BusinessFinder;

use App\Models\BusinessLead;
use App\Models\User;
use App\Services\LeadTransferService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Business Finder export. CSV / Excel go through the existing LeadTransferService writer;
 * PDF uses dompdf. Do-not-contact records are left out unless explicitly requested.
 */
class BusinessLeadExporter
{
    public const HEADERS = [
        'ID', 'Business Name', 'Category', 'Contact Person', 'Phone', 'Email', 'Website', 'Website Status',
        'Website Last Checked', 'HTTP Status', 'Contact Status', 'Contact Verified On', 'Contact Source',
        'Address', 'Area', 'City', 'District', 'PIN Code', 'Source URL', 'CRM Lead ID', 'Lead Status',
        'Priority', 'Next Follow-up Date', 'Next Follow-up Time', 'Notes', 'Created At',
    ];

    public function __construct(private readonly LeadTransferService $transfer) {}

    /** @return array{path: string, count: int} */
    public function export(User $user, array $filters, string $format): array
    {
        $query = $this->query($user, $filters);
        $count = (clone $query)->count();

        if ($format === 'pdf') {
            $max = (int) config('business_finder.pdf_max_rows', 1000);
            if ($count > $max) {
                throw new RuntimeException("PDF export is limited to $max records ($count matched). Narrow the filters or export Excel/CSV.");
            }
            $path = $this->tempPath('pdf');
            Pdf::loadView('exports.business_leads', [
                'leads' => $query->get(),
                'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== []),
                'user' => $user,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape')->setOption('enable_font_subsetting', true)->save($path);

            return ['path' => $path, 'count' => $count];
        }

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $b) {
                yield $this->row($b);
            }
        })();

        return ['path' => $this->transfer->writeSheet($format, self::HEADERS, $rows, 'business_leads_'), 'count' => $count];
    }

    private function query(User $user, array $filters): Builder
    {
        $q = BusinessLeadQuery::build($user, $filters)->with('lead');
        if (! filter_var($filters['include_do_not_contact'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $q->contactable();
        }

        return $q;
    }

    private function row(BusinessLead $b): array
    {
        $lead = $b->lead;
        $next = $lead?->next_followup_at;

        return [
            $b->id,
            $b->business_name,
            $b->category->value,
            (string) $b->contact_person,
            (string) $b->phone,
            (string) $b->email,
            (string) $b->website_url,
            $b->website_status->value,
            $b->website_checked_at?->format('Y-m-d H:i') ?? '',
            $b->website_http_status ?? '',
            $b->contact_status->value,
            $b->contact_verified_at?->format('Y-m-d') ?? '',
            (string) $b->contact_source,
            (string) $b->address,
            (string) $b->area,
            (string) $b->city,
            (string) $b->district,
            (string) $b->pincode,
            (string) $b->source_url,
            $lead?->id ?? '',
            $lead?->status->value ?? '',
            $lead?->priority->value ?? '',
            $next?->format('Y-m-d') ?? '',
            $next?->format('H:i') ?? '',
            (string) $b->notes,
            $b->created_at->format('Y-m-d H:i'),
        ];
    }

    private function tempPath(string $ext): string
    {
        $base = tempnam(sys_get_temp_dir(), 'business_leads_');
        @unlink($base);

        return "$base.$ext";
    }
}
