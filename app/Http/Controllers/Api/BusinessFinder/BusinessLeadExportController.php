<?php

namespace App\Http\Controllers\Api\BusinessFinder;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\BusinessFinder\BusinessLeadExporter;
use App\Services\BusinessFinder\BusinessLeadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BusinessLeadExportController extends Controller
{
    private const FORMATS = [
        'csv' => ['ext' => 'csv', 'mime' => 'text/csv'],
        'excel' => ['ext' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pdf' => ['ext' => 'pdf', 'mime' => 'application/pdf'],
    ];

    public function __construct(private readonly BusinessLeadExporter $exporter) {}

    #[OA\Get(
        path: '/api/business-finder/export/{format}', summary: 'Export businesses as CSV, Excel or PDF',
        description: "Only your own records. Do-not-contact records are excluded unless `include_do_not_contact=1`.\n\n"
            ."Presets (`scope`): `all`, `verified_contacts`, `no_website` (marked No website found after research), `followups` (saved leads with a scheduled call).\n"
            .'Selected records: `ids[]=1&ids[]=5`. All list filters also apply. PDF is limited to 1000 records.',
        tags: ['Business Finder leads'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'format', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel', 'pdf'])),
            new OA\Parameter(name: 'scope', in: 'query', schema: new OA\Schema(type: 'string', default: 'all', enum: ['all', 'verified_contacts', 'no_website', 'followups'])),
            new OA\Parameter(name: 'ids[]', in: 'query', description: 'Selected business IDs', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'city', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'district', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'website_status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'contact_status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'contact', in: 'query', schema: new OA\Schema(type: 'string', enum: ['phone_available', 'phone_missing', 'pending', 'verified'])),
            new OA\Parameter(name: 'saved', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'lead_status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'include_do_not_contact', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File download', content: [
                new OA\MediaType(mediaType: 'text/csv', schema: new OA\Schema(type: 'string', format: 'binary')),
                new OA\MediaType(mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', schema: new OA\Schema(type: 'string', format: 'binary')),
                new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary')),
            ]),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, description: 'Too many exports'),
        ]
    )]
    public function export(Request $request, string $format): BinaryFileResponse|JsonResponse
    {
        $filters = $request->validate(BusinessLeadQuery::rules());
        $spec = self::FORMATS[$format];

        try {
            $result = $this->exporter->export($request->user(), $filters, $spec['ext']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['format' => [$e->getMessage()]]], 422);
        }
        AuditLog::record('business_lead.exported', null, ['format' => $format, 'count' => $result['count'], 'filters' => $filters]);

        $name = 'business_leads_'.now()->format('Ymd_His').'.'.$spec['ext'];

        return response()->download($result['path'], $name, ['Content-Type' => $spec['mime']])->deleteFileAfterSend();
    }
}
