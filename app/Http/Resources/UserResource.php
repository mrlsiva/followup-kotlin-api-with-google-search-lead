<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Sales Admin'),
        new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'manager', 'sales', 'researcher'], example: 'sales'),
        new OA\Property(property: 'can_use_crm', type: 'boolean', example: true,
            description: 'false for researchers: show only the Business Finder screens (lead, follow-up and dashboard endpoints return 403)'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'can_use_crm' => $this->canUseCrm(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
