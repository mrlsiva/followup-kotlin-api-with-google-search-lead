<?php

namespace App\Providers;

use App\Models\Followup;
use App\Models\Lead;
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
    }
}
