<?php

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => 'users',
    ],

    'guards' => [
        // Session-based, cookie-backed guard used by the Inertia/Vue web
        // portal (routes/web.php, protected by the 'auth' middleware).
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Bearer-token guard used by /api/v1/* for pure API clients (the
        // future mobile app, third-party integrations). This is separate
        // from 'web' on purpose -- a portal browser session and a mobile
        // app's long-lived API token are different credentials with
        // different lifetimes and revocation semantics.
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
