<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'torrents' => [
            'driver' => 'local',
            'root' => storage_path('app/torrents'),
            'throw' => false,
        ],

        'nfo' => [
            'driver' => 'local',
            'root' => storage_path('app/nfo'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'images' => [
            'driver' => 'local',
            'root' => storage_path('app/images'),
            'url' => env('APP_URL').'/storage/images',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
        public_path('storage/images') => storage_path('app/images'),
    ],
];
