<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Global OpenAPI (Swagger) definitions: info, server, auth scheme, tags and shared components.
 * Endpoint docs live as attributes on the controllers in app/Http/Controllers/Api.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Follow-up CRM API',
    description: "REST API for the Lead Generation & Follow-up Android app.\n\n"
        ."**Auth:** call `POST /api/auth/login` (demo: `admin@example.com` / `password`), copy the `token`, click **Authorize** and paste it.\n\n"
        ."**Dates:** send `YYYY-MM-DD` dates and `HH:MM` (24h) times; responses include ISO-8601 timestamps plus separate date/time fields.\n\n"
        ."**Follow-ups:** a lead has at most one *pending* follow-up (its next follow-up). Completed follow-ups form the lead's timeline."
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'XAMPP (local)')]
#[OA\SecurityScheme(securityScheme: 'sanctum', type: 'http', scheme: 'bearer', bearerFormat: 'Sanctum token', description: 'Paste the token from /api/auth/login')]
#[OA\Tag(name: 'Auth', description: 'Register, login and token management')]
#[OA\Tag(name: 'Dashboard', description: 'Counters and today’s work')]
#[OA\Tag(name: 'Leads', description: 'Lead CRUD and search')]
#[OA\Tag(name: 'Lead follow-ups', description: 'Timeline and notes of a single lead')]
#[OA\Tag(name: 'Follow-ups', description: 'Today / upcoming / overdue lists, complete, reschedule, reminders')]
#[OA\Tag(name: 'Import / Export', description: 'Excel (.xlsx) and CSV')]
#[OA\Tag(name: 'Meta', description: 'Dropdown options')]
#[OA\Response(response: 'Unauthenticated', description: 'Missing or invalid token', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')]))]
#[OA\Response(response: 'NotFound', description: 'Not found (or belongs to another user)', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Record not found.')]))]
#[OA\Response(response: 'ValidationError', description: 'Validation failed', content: new OA\JsonContent(properties: [
    new OA\Property(property: 'message', type: 'string', example: 'The name field is required.'),
    new OA\Property(property: 'errors', type: 'object', example: ['name' => ['The name field is required.']]),
]))]
#[OA\Parameter(parameter: 'page', name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1))]
#[OA\Parameter(parameter: 'per_page', name: 'per_page', in: 'query', required: false, description: '1–100', schema: new OA\Schema(type: 'integer', default: 20))]
class OpenApiSpec
{
}
