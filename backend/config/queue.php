<?php

return [
    // No persistent worker process on shared hosting (no Supervisor/
    // Horizon) -- 'database' driver + a cron job running
    // `artisan queue:work --stop-when-empty` every minute is the standard
    // shared-hosting-compatible pattern. See the cPanel deployment guide
    // in the README for the exact cron entry.
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => ['driver' => 'sync'],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
