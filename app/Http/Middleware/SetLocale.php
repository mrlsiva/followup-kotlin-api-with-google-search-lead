<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Web panel language: the user's saved choice, else the session (login page), else English. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', 'en');
        if (array_key_exists($locale, config('crm.locales'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
