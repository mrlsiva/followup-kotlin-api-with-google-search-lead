<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportLeadsRequest;
use App\Services\LeadTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadTransferController extends Controller
{
    public function __construct(private readonly LeadTransferService $transfer) {}

    #[OA\Get(
        path: '/api/leads/export', summary: 'Download all leads as Excel or CSV', tags: ['Import / Export'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'format', in: 'query', schema: new OA\Schema(type: 'string', default: 'xlsx', enum: ['xlsx', 'csv']))],
        responses: [
            new OA\Response(response: 200, description: 'File download', content: [
                new OA\MediaType(mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', schema: new OA\Schema(type: 'string', format: 'binary')),
                new OA\MediaType(mediaType: 'text/csv', schema: new OA\Schema(type: 'string', format: 'binary')),
            ]),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function export(Request $request): BinaryFileResponse
    {
        $request->validate(['format' => ['nullable', 'in:xlsx,csv']]);
        $format = $request->input('format', 'xlsx');
        $path = $this->transfer->export($request->user(), $format);
        $name = 'leads_'.now()->format('Ymd_His').'.'.$format;
        $mime = $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv';

        return response()->download($path, $name, ['Content-Type' => $mime])->deleteFileAfterSend();
    }

    #[OA\Post(
        path: '/api/leads/import', summary: 'Import leads from Excel (.xlsx) or CSV',
        description: "First row must be a header. Required column: **Customer Name**. Optional: ID, Mobile Number, Email, Company Name, Lead Source, Status, Priority, Notes, Next Follow-up Date, Next Follow-up Time.\n\nRows matching an existing lead by ID or mobile number update it; others are created. Use the export file as a template.",
        tags: ['Import / Export'], security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
            required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')]
        ))),
        responses: [
            new OA\Response(response: 200, description: 'Import summary', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Import complete.'),
                new OA\Property(property: 'created', type: 'integer', example: 10),
                new OA\Property(property: 'updated', type: 'integer', example: 2),
                new OA\Property(property: 'skipped', type: 'integer', example: 1),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'), example: ['Row 5: missing customer name']),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function import(ImportLeadsRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        try {
            $result = $this->transfer->import($request->user(), $file->getRealPath(), $ext);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        return response()->json(['message' => 'Import complete.', ...$result]);
    }
}
