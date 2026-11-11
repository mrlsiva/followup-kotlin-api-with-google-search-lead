<?php

namespace App\Providers;

use App\Models\BusinessLead;
use App\Models\Followup;
use App\Models\Lead;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Route model binding scoped to the authenticated user: other users' records are a 404.
        Route::bind('lead', fn (string $value) => Lead::where('user_id', auth()->id())->findOrFail((int) $value));
        Route::bind('followup', fn (string $value) => Followup::where('user_id', auth()->id())->findOrFail((int) $value));
        Route::bind('businessLead', fn (string $value) => BusinessLead::where('user_id', auth()->id())->findOrFail((int) $value));

        // Business Lead Finder rate limits (per user)
        $key = fn (Request $r) => $r->user()?->id ?: $r->ip();
        RateLimiter::for('business-finder', fn (Request $r) => Limit::perMinute(120)->by('bf:'.$key($r)));
        RateLimiter::for('website-check', fn (Request $r) => Limit::perMinute(10)->by('bf-web:'.$key($r))
            ->response(fn () => response()->json(['message' => 'Too many website checks. Please wait a minute.'], 429)));
        RateLimiter::for('exports', fn (Request $r) => Limit::perMinute(10)->by('bf-export:'.$key($r))
            ->response(fn () => response()->json(['message' => 'Too many exports. Please wait a minute.'], 429)));
    }
}
