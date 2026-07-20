<?php

return [
    // No Redis on shared cPanel hosting -- 'database' driver needs the
    // `cache` table (migration included) but nothing else to install.
    'default' => env('CACHE_STORE', 'database'),

    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
            'table' => 'cache',
            'lock_connection' => null,
            'lock_table' => 'cache_locks',
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'npvams_cache_'),
];
