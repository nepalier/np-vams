<?php

declare(strict_types=1);

// Shared cPanel hosting has no MinIO/S3 available by default -- the
// `private_documents` disk defaults to local storage OUTSIDE the public
// webroot (storage/app/private, same as Laravel's own 'local' disk),
// which is genuinely private since Nginx/Apache never serves it directly;
// downloads always go through a signed, authenticated controller route,
// never a direct public URL. If you later want S3-compatible storage
// (DigitalOcean Spaces, Backblaze B2, Cloudflare R2), set
// DOCUMENTS_DISK=s3 in .env and fill in the AWS_* variables below --
// the 's3' disk definition is ready to go, just unused by default.
return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Default private-document disk on shared hosting -- see the
        // module-level comment above.
        'private_documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private/documents'),
            'serve' => false,
            'throw' => false,
        ],

        // Optional upgrade path: any S3-compatible provider. Unused unless
        // DOCUMENTS_DISK=s3 is set in .env.
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
