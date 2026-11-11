<?php

namespace App\Http\Resources;

use App\Services\BusinessFinder\SearchLinkBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'BusinessSearch',
    description: 'A search-history entry. `links` regenerates the manual search links so the search can be re-opened.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'query', type: 'string', example: 'Hospitals in Chennai'),
        new OA\Property(property: 'business_name', type: 'string', nullable: true),
        new OA\Property(property: 'match_type', type: 'string', enum: ['exact', 'partial']),
        new OA\Property(property: 'keywords', type: 'string', nullable: true),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: 'Hospitals'),
        new OA\Property(property: 'state', type: 'string', example: 'Tamil Nadu'),
        new OA\Property(property: 'district', type: 'string', nullable: true),
        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Chennai'),
        new OA\Property(property: 'area', type: 'string', nullable: true),
        new OA\Property(property: 'pincode', type: 'string', nullable: true),
        new OA\Property(property: 'quantity', type: 'integer', example: 20),
        new OA\Property(property: 'website_filter', type: 'string', nullable: true),
        new OA\Property(property: 'contact_filter', type: 'string', nullable: true),
        new OA\Property(property: 'businesses_saved', type: 'integer', description: 'Businesses added from this search', example: 4),
        new OA\Property(property: 'links', ref: '#/components/schemas/SearchLinks'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class BusinessSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'query' => $this->query,
            'business_name' => $this->business_name,
            'match_type' => $this->match_type,
            'keywords' => $this->keywords,
            'category' => $this->category?->value,
            'state' => $this->state,
            'district' => $this->district,
            'city' => $this->city,
            'area' => $this->area,
            'pincode' => $this->pincode,
            'quantity' => $this->quantity,
            'website_filter' => $this->website_filter,
            'contact_filter' => $this->contact_filter,
            'businesses_saved' => $this->whenCounted('businessLeads'),
            'links' => app(SearchLinkBuilder::class)->build([...$this->resource->only([
                'business_name', 'match_type', 'keywords', 'district', 'city', 'area', 'pincode', 'quantity',
            ]), 'category' => $this->category?->value]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
