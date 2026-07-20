<?php

return [
    // 'file' driver: zero setup, works on any shared host with a writable
    // storage/ directory -- no DB table, no Redis. Fine for a single-server
    // shared-hosting deployment; switch to 'database' only if load-balancing
    // across multiple app servers later.
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'npvams_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => (bool) env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
