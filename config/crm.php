<?php

return [

    // Public sign-up (web /register and POST /api/auth/register). Off by default: the admin creates users.
    'allow_registration' => (bool) env('ALLOW_REGISTRATION', false),

    // Languages offered in the web panel
    'locales' => [
        'en' => 'English',
        'ta' => 'தமிழ்',
    ],
];
