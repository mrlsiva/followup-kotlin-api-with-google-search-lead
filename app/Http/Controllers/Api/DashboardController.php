<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FollowupResource;
use App\Http\Resources\LeadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/dashboard', summary: 'Dashboard counters, today’s follow-ups and recent leads', tags: ['Dashboard'], security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'stats', type: 'object', properties: [
                    new OA\Property(property: 'total_leads', type: 'integer', example: 48),
                    new OA\Property(property: 'today_followups', type: 'integer', example: 5),
                    new OA\Property(property: 'completed_followups', type: 'integer', example: 120),
                    new OA\Property(property: 'pending_followups', type: 'integer', example: 31),
                    new OA\Property(property: 'overdue_followups', type: 'integer', example: 3),
                ]),
                new OA\Property(property: 'leads_by_status', type: 'object', example: ['New' => 10, 'Interested' => 8, 'Follow-up' => 20, 'Won' => 6, 'Lost' => 4]),
                new OA\Property(property: 'today', type: 'array', items: new OA\Items(ref: '#/components/schemas/Followup')),
                new OA\Property(property: 'overdue', type: 'array', description: 'Up to 10, oldest first', items: new OA\Items(ref: '#/components/schemas/Followup')),
                new OA\Property(property: 'recent_leads', type: 'array', items: new OA\Items(ref: '#/components/schemas/Lead')),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $followups = fn () => $user->followups()->activeLead();

        $byStatus = $user->leads()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'stats' => [
                'total_leads' => $user->leads()->count(),
                'today_followups' => $followups()->today()->count(),
                'completed_followups' => $followups()->completed()->count(),
                'pending_followups' => $followups()->pending()->count(),
                'overdue_followups' => $followups()->overdue()->count(),
            ],
            'leads_by_status' => collect(LeadStatus::values())->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)]),
            'today' => FollowupResource::collection($followups()->today()->with('lead')->orderBy('scheduled_at')->get()),
            'overdue' => FollowupResource::collection($followups()->overdue()->with('lead')->orderBy('scheduled_at')->limit(10)->get()),
            'recent_leads' => LeadResource::collection($user->leads()->latest()->limit(5)->get()),
        ]);
    }
}
