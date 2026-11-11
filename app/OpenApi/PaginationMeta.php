<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/** Laravel paginator `meta` block returned by list endpoints. */
#[OA\Schema(schema: 'PaginationMeta', properties: [
    new OA\Property(property: 'current_page', type: 'integer', example: 1),
    new OA\Property(property: 'last_page', type: 'integer', example: 3),
    new OA\Property(property: 'per_page', type: 'integer', example: 20),
    new OA\Property(property: 'total', type: 'integer', example: 47),
])]
class PaginationMeta
{
}
