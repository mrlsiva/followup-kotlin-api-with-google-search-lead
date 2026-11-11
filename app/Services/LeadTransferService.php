<?php

namespace App\Services;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/** Export / import of leads as Excel (.xlsx) or CSV, using OpenSpout (streaming, low memory). */
class LeadTransferService
{
    public const HEADERS = [
        'ID', 'Customer Name', 'Mobile Number', 'Email', 'Company Name', 'Lead Source', 'Status',
        'Priority', 'Notes', 'Next Follow-up Date', 'Next Follow-up Time', 'Created At',
    ];

    public function __construct(private readonly FollowupService $followups) {}

    /** Writes the user's leads to a temp file and returns its path. */
    public function export(User $user, string $format): string
    {
        $rows = (function () use ($user) {
            foreach ($user->leads()->orderBy('name')->lazy(500) as $lead) {
                yield [
                    $lead->id,
                    $lead->name,
                    (string) $lead->mobile,
                    (string) $lead->email,
                    (string) $lead->company,
                    $lead->source->value,
                    $lead->status->value,
                    $lead->priority->value,
                    (string) $lead->notes,
                    $lead->next_followup_at?->format('Y-m-d') ?? '',
                    $lead->next_followup_at?->format('H:i') ?? '',
                    $lead->created_at->format('Y-m-d H:i'),
                ];
            }
        })();

        return $this->writeSheet($format, self::HEADERS, $rows, 'leads_');
    }

    /**
     * Streams a header row + rows to a temp .xlsx / .csv file and returns its path.
     * Shared by the lead export and the Business Finder export.
     *
     * @param  iterable<array<int, scalar|null>>  $rows
     */
    public function writeSheet(string $format, array $headers, iterable $rows, string $prefix = 'export_'): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        @unlink($base);
        $path = $base.'.'.$format;
        // CSV writer adds a UTF-8 BOM by default, so Excel shows non-ASCII names correctly
        $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter;
        $writer->openToFile($path);

        $headerStyle = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1A5FB4');
        $writer->addRow(Row::fromValues($headers, $format === 'xlsx' ? $headerStyle : null));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    /**
     * Imports leads. Rows whose ID (same user) or mobile number matches an existing lead update it;
     * others are created. Header names are matched loosely so hand-made sheets work.
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(User $user, string $path, string $extension): array
    {
        $reader = $extension === 'xlsx' ? new XlsxReader : new CsvReader;
        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new RuntimeException('Could not read the file. Upload a valid .xlsx or .csv file.');
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $map = null;
        $line = 0;

        try {
            DB::transaction(function () use ($reader, $user, &$result, &$map, &$line) {
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $line++;
                        $cells = $row->toArray();
                        if ($map === null) {
                            $map = $this->mapHeaders($cells);

                            continue;
                        }
                        if (count(array_filter($cells, fn ($c) => trim((string) $this->text($c)) !== '')) === 0) {
                            continue; // blank row
                        }
                        $this->importRow($user, $cells, $map, $line, $result);
                    }
                    break; // first sheet only
                }
            });
        } finally {
            $reader->close();
        }

        if ($map === null) {
            throw new RuntimeException('The file is empty.');
        }

        return $result;
    }

    private function importRow(User $user, array $cells, array $map, int $line, array &$result): void
    {
        $get = fn (string $key) => isset($map[$key]) ? ($cells[$map[$key]] ?? null) : null;

        $name = trim($this->text($get('name')));
        if ($name === '') {
            $result['skipped']++;
            $result['errors'][] = "Row $line: missing customer name";

            return;
        }
        $mobile = trim($this->text($get('mobile')));
        $email = trim($this->text($get('email')));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['errors'][] = "Row $line: invalid email \"$email\" ignored";
            $email = '';
        }

        // Only non-empty cells are applied, so updating an existing lead never blanks its data.
        $company = trim($this->text($get('company')));
        $notes = trim($this->text($get('notes')));
        $source = trim($this->text($get('source')));
        $status = trim($this->text($get('status')));
        $priority = trim($this->text($get('priority')));
        $data = array_filter([
            'name' => mb_substr($name, 0, 150),
            'mobile' => $mobile !== '' ? mb_substr($mobile, 0, 20) : null,
            'email' => $email !== '' ? $email : null,
            'company' => $company !== '' ? mb_substr($company, 0, 150) : null,
            'source' => $source !== '' ? LeadSource::fromLoose($source, LeadSource::Other) : null,
            'status' => $status !== '' ? LeadStatus::fromLoose($status, LeadStatus::New) : null,
            'priority' => $priority !== '' ? LeadPriority::fromLoose($priority, LeadPriority::Medium) : null,
            'notes' => $notes !== '' ? $notes : null,
        ], fn ($v) => $v !== null);

        $existing = null;
        if (($id = (int) $this->text($get('id'))) > 0) {
            $existing = $user->leads()->find($id);
        }
        if (! $existing && $mobile !== '') {
            $existing = $user->leads()->where('mobile', $mobile)->first();
        }

        if ($existing) {
            $existing->update($data);
            $lead = $existing;
            $result['updated']++;
        } else {
            $lead = $user->leads()->create($data);
            $result['created']++;
        }

        $next = $this->dateTime($get('next_date'), $get('next_time'));
        if ($next && $next->isFuture()) {
            $this->followups->syncNextFollowup($lead, $next);
        }
    }

    /** Header text → column index, using loose matching. */
    private function mapHeaders(array $cells): array
    {
        $aliases = [
            'id' => ['id'],
            'name' => ['customer name', 'name', 'lead name', 'customer', 'full name'],
            'mobile' => ['mobile number', 'mobile', 'phone', 'phone number', 'contact', 'contact number'],
            'email' => ['email', 'email address', 'e-mail'],
            'company' => ['company name', 'company', 'business', 'organization'],
            'source' => ['lead source', 'source'],
            'status' => ['status', 'lead status'],
            'priority' => ['priority'],
            'notes' => ['notes', 'note', 'remarks', 'comments'],
            'next_date' => ['next follow-up date', 'next followup date', 'follow-up date', 'followup date', 'next follow-up', 'next followup'],
            'next_time' => ['next follow-up time', 'next followup time', 'follow-up time', 'followup time'],
        ];
        $headers = array_map(fn ($h) => strtolower(trim($this->text($h))), $cells);
        $map = [];
        foreach ($aliases as $key => $names) {
            foreach ($names as $alias) {
                $idx = array_search($alias, $headers, true);
                if ($idx !== false) {
                    $map[$key] = $idx;
                    break;
                }
            }
        }
        if (! isset($map['name'])) {
            throw new RuntimeException('Missing a "Customer Name" column in the header row.');
        }

        return $map;
    }

    private function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i'),
            is_float($value) && floor($value) == $value => (string) (int) $value, // phone numbers typed as numbers
            default => (string) $value,
        };
    }

    private function dateTime(mixed $date, mixed $time): ?Carbon
    {
        try {
            if ($date instanceof DateTimeInterface) {
                $dt = Carbon::instance($date)->shiftTimezone(config('app.timezone'));
            } elseif (trim((string) $date) !== '') {
                $dt = Carbon::parse(trim((string) $date));
            } else {
                return null;
            }
            if ($time instanceof DateTimeInterface) {
                $dt->setTime((int) $time->format('H'), (int) $time->format('i'));
            } elseif (preg_match('/^(\d{1,2}):(\d{2})/', trim((string) $time), $m)) {
                $dt->setTime((int) $m[1], (int) $m[2]);
            } elseif ($dt->format('H:i') === '00:00') {
                $dt->setTime(10, 0);
            }

            return $dt->seconds(0);
        } catch (\Throwable) {
            return null;
        }
    }
}
