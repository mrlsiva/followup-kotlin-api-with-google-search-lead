<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CallRecordingRequest;
use App\Http\Resources\FollowupResource;
use App\Models\Followup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Call recording of a completed follow-up (a timeline note).
 * The recording can also be sent with the note itself (multipart field `recording` on
 * POST /leads/{lead}/followups and POST /followups/{followup}/complete); this endpoint is for
 * uploading it later, retrying a failed upload or replacing it.
 */
class CallRecordingController extends Controller
{
    #[OA\Post(
        path: '/api/followups/{followup}/recording', summary: 'Upload or replace the call recording of a note',
        description: 'multipart/form-data, field `recording`. Formats: m4a (recommended, AAC), mp4, aac, mp3, amr, 3gp, ogg, opus, wav, webm. Max size: CALL_RECORDING_MAX_MB (default 25 MB). Only completed follow-ups (notes) can have a recording.',
        tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'followup', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
            required: ['recording'],
            properties: [new OA\Property(property: 'recording', type: 'string', format: 'binary')]
        ))),
        responses: [
            new OA\Response(response: 200, description: 'Stored', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Followup')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Pending follow-ups cannot have a recording (complete it first)'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function store(CallRecordingRequest $request, Followup $followup): JsonResponse|FollowupResource
    {
        if ($followup->isPending()) {
            return response()->json(['message' => 'Complete the follow-up before adding a recording.'], 409);
        }
        $followup->attachRecording($request->file('recording'));

        return new FollowupResource($followup->refresh());
    }

    #[OA\Get(
        path: '/api/followups/{followup}/recording', summary: 'Play / download the call recording',
        description: 'Returns the audio file (Content-Type e.g. audio/mp4). Supports HTTP Range requests for seeking. Add `?download=1` for Content-Disposition: attachment.',
        tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'followup', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'download', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Audio file', content: new OA\MediaType(mediaType: 'audio/*', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, description: 'No such follow-up or no recording'),
        ]
    )]
    public function show(Request $request, Followup $followup): Response
    {
        abort_unless($followup->hasRecording(), 404, 'This note has no recording.');

        return $followup->recordingResponse($request->boolean('download'));
    }

    #[OA\Delete(
        path: '/api/followups/{followup}/recording', summary: 'Delete the call recording (the note stays)',
        tags: ['Follow-ups'], security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'followup', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Followup')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(Followup $followup): FollowupResource
    {
        $followup->removeRecording();

        return new FollowupResource($followup->refresh());
    }
}
