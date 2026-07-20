<?php

declare(strict_types=1);

// Application-specific configuration, kept separate from Laravel's own
// config/auth.php and config/sanctum.php so this file can be dropped into
// a standard `laravel new` skeleton without editing framework config files.
return [
    'login_throttle' => [
        'max_attempts' => (int) env('LOGIN_THROTTLE_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('LOGIN_THROTTLE_DECAY_MINUTES', 15),
    ],

    'sanctum_token_expiration_minutes' => (int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 480),

    'mfa_enabled_by_default' => (bool) env('MFA_ENABLED', true),

    'password_min_length' => (int) env('PASSWORD_MIN_LENGTH', 10),

    'documents' => [
        'disk' => env('DOCUMENTS_DISK', 'private_documents'),
        'max_upload_bytes' => 25 * 1024 * 1024,
    ],

    'fiscal_year' => [
        // Calendar anchor only -- the authoritative fiscal-year calendar is
        // the DB-driven fiscal_years table (see MasterDataSeeder), never
        // hard-coded logic. This value is used solely to pick a sane
        // display default before any fiscal year row exists.
        'start_month_bs' => (int) env('NEPALI_FISCAL_YEAR_START_MONTH', 4),
    ],
];
