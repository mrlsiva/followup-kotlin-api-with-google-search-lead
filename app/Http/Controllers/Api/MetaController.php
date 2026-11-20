<?php

namespace App\Http\Controllers\Api;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class MetaController extends Controller
{
    #[OA\Get(
        path: '/api/options', summary: 'Allowed values for dropdowns (no auth)', tags: ['Meta'],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'sources', type: 'array', items: new OA\Items(type: 'string'), example: ['WhatsApp', 'Facebook', 'Website', 'Referral', 'Call', 'Walk-in', 'Other']),
            new OA\Property(property: 'statuses', type: 'array', items: new OA\Items(type: 'string'), example: ['New', 'Interested', 'Follow-up', 'Won', 'Lost']),
            new OA\Property(property: 'priorities', type: 'array', items: new OA\Items(type: 'string'), example: ['High', 'Medium', 'Low']),
            new OA\Property(property: 'call_statuses', type: 'array', items: new OA\Items(type: 'string'), example: ['Interested', 'Not Answered', 'Callback', 'Closed']),
            new OA\Property(property: 'call_directions', type: 'array', items: new OA\Items(type: 'string'), example: ['outgoing', 'incoming']),
            new OA\Property(property: 'recording_formats', type: 'array', items: new OA\Items(type: 'string'), example: ['m4a', 'mp4', 'aac', 'mp3', 'amr', '3gp', 'ogg', 'opus', 'wav', 'webm']),
            new OA\Property(property: 'recording_max_mb', type: 'integer', example: 25),
        ]))]
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'sources' => LeadSource::values(),
            'statuses' => LeadStatus::values(),
            'priorities' => LeadPriority::values(),
            'call_statuses' => CallStatus::values(),
            'call_directions' => CallDirection::values(),
            'recording_formats' => array_keys(config('crm.recordings.formats')),
            'recording_max_mb' => intdiv(config('crm.recordings.max_kb'), 1024),
        ]);
    }
}
