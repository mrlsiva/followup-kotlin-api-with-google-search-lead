<?php

return [

    // Public sign-up (web /register and POST /api/auth/register). Off by default: the admin creates users.
    'allow_registration' => (bool) env('ALLOW_REGISTRATION', false),

    // Languages offered in the web panel
    'locales' => [
        'en' => 'English',
        'ta' => 'தமிழ்',
    ],

    // Call recordings uploaded by the app (stored privately, served only to the owner / their manager / admin)
    'recordings' => [
        'disk' => env('CALL_RECORDING_DISK', 'local'),
        'max_kb' => (int) env('CALL_RECORDING_MAX_MB', 25) * 1024,
        // Allowed file extensions => Content-Type used when serving the file.
        // m4a (AAC) is the recommended format: small, and plays everywhere (Android MediaPlayer, browsers).
        'formats' => [
            'm4a' => 'audio/mp4',
            'mp4' => 'audio/mp4',
            'aac' => 'audio/aac',
            'mp3' => 'audio/mpeg',
            'amr' => 'audio/amr',
            '3gp' => 'audio/3gpp',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/ogg',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
        ],
        // Content-based (detected) MIME types accepted for those files
        'detected_mimes' => [
            'audio/*', 'video/mp4', 'video/3gpp', 'video/3gpp2', 'video/webm', 'video/quicktime', 'application/ogg',
        ],
    ],
];
