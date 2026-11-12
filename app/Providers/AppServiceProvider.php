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
        // Route model binding scoped to the authenticated user: records outside the user's reach are a 404.
        // API (mobile app): own records only. Web panel: own, team (managers) or all (admins) may be VIEWED;
        // web controllers still allow changes only by the owner.
        $scoped = function (string $model) {
            return function (string $value) use ($model) {
                $user = auth()->user();
                $query = $model::query();
                if (request()->is('api/*') || ! $user) {
                    $query->where('user_id', auth()->id());
                } elseif (($ids = $user->visibleUserIds()) !== null) {
                    $query->whereIn('user_id', $ids);
                }

                return $query->findOrFail((int) $value);
            };
        };
        Route::bind('lead', $scoped(Lead::class));
        Route::bind('followup', $scoped(Followup::class));
        Route::bind('businessLead', $scoped(BusinessLead::class));
        Route::bind('managedUser', fn (string $value) => \App\Models\User::findOrFail((int) $value));
        Route::bind('category', fn (string $value) => \App\Models\FinderCategory::findOrFail((int) $value));
        \App\Support\Categories::flush();   // fresh category list for every app instance

        // Bootstrap 5 pagination links in the web panel
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        // Sidebar badge: follow-ups due today or overdue
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            if ($user = auth()->user()) {
                $view->with('dueCount', $user->followups()->activeLead()->pending()
                    ->where('scheduled_at', '<=', now()->endOfDay())->count());
            }
        });

        // Business Lead Finder rate limits (per user)
        $key = fn (Request $r) => $r->user()?->id ?: $r->ip();
        RateLimiter::for('business-finder', fn (Request $r) => Limit::perMinute(120)->by('bf:'.$key($r)));
        RateLimiter::for('website-check', fn (Request $r) => Limit::perMinute(10)->by('bf-web:'.$key($r))
            ->response(fn () => response()->json(['message' => 'Too many website checks. Please wait a minute.'], 429)));
        RateLimiter::for('exports', fn (Request $r) => Limit::perMinute(10)->by('bf-export:'.$key($r))
            ->response(fn () => response()->json(['message' => 'Too many exports. Please wait a minute.'], 429)));
    }
}
