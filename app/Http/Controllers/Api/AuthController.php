<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AuthToken', properties: [
    new OA\Property(property: 'token', type: 'string', example: '1|qXk0...'),
    new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
    new OA\Property(property: 'user', ref: '#/components/schemas/User'),
])]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/register', summary: 'Create an account and get a token', tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'email', 'password', 'password_confirmation'], properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Sales Admin'),
            new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
            new OA\Property(property: 'password', type: 'string', example: 'secret123'),
            new OA\Property(property: 'password_confirmation', type: 'string', example: 'secret123'),
            new OA\Property(property: 'device_name', type: 'string', example: 'Pixel 8'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Registered', content: new OA\JsonContent(ref: '#/components/schemas/AuthToken')),
            new OA\Response(response: 403, description: 'Registration is closed (ALLOW_REGISTRATION=false); the admin creates users in the web panel'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        if (! config('crm.allow_registration')) {
            return response()->json(['message' => 'Registration is closed. Ask your administrator for an account.'], 403);
        }
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json($this->tokenPayload($user, $request->input('device_name')), 201);
    }

    #[OA\Post(
        path: '/api/auth/login', summary: 'Log in and get a bearer token', tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'password'], properties: [
            new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
            new OA\Property(property: 'password', type: 'string', example: 'password'),
            new OA\Property(property: 'device_name', type: 'string', example: 'Pixel 8'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Logged in', content: new OA\JsonContent(ref: '#/components/schemas/AuthToken')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'This account has been disabled. Contact your administrator.']);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json($this->tokenPayload($user, $request->input('device_name')));
    }

    #[OA\Get(
        path: '/api/auth/me', summary: 'Current user', tags: ['Auth'], security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    #[OA\Post(
        path: '/api/auth/logout', summary: 'Revoke the current token', tags: ['Auth'], security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Logged out.')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function tokenPayload(User $user, ?string $device): array
    {
        return [
            'token' => $user->createToken($device ?: 'android-app')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ];
    }
}
