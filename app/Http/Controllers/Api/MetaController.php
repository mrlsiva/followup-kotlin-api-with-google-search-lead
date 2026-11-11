<?php

namespace App\Http\Controllers\Api;

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
        ]))]
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'sources' => LeadSource::values(),
            'statuses' => LeadStatus::values(),
            'priorities' => LeadPriority::values(),
            'call_statuses' => CallStatus::values(),
        ]);
    }
}
