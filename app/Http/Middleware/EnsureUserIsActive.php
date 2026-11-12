<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Disabled accounts are signed out of the web panel and get 403 on the API. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'This account has been disabled. Contact your administrator.'], 403);
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('This account has been disabled. Contact your administrator.')]);
        }

        return $next($request);
    }
}
