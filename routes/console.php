<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// php artisan crm:make-admin someone@example.com  → give a user the admin role (and enable the account)
Artisan::command('crm:make-admin {email}', function (string $email) {
    $user = \App\Models\User::where('email', $email)->first();
    if (! $user) {
        $this->error("No user with email $email.");

        return 1;
    }
    $user->forceFill(['role' => 'admin', 'manager_id' => null, 'is_active' => true])->save();
    $this->info("{$user->name} is now an admin.");

    return 0;
})->purpose('Give a user the admin role');
