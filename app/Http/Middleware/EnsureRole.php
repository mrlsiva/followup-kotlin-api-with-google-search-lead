<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * role:admin  – administrators only
 * role:team   – administrators and managers (team views / reports)
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        $allowed = match ($ability) {
            'admin' => $user?->isAdmin(),
            'team' => $user?->canViewTeam(),
            default => false,
        };
        abort_unless($allowed, 403);

        return $next($request);
    }
}
