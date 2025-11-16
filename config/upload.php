<?php

declare(strict_types=1);

return [
    'torrents' => [
        'disk' => env('UPLOAD_TORRENTS_DISK', 'torrents'),
        'directory' => env('UPLOAD_TORRENTS_DIRECTORY', 'torrents'),
        'max_kilobytes' => (int) env('UPLOAD_TORRENTS_MAX_KB', 1024),
        'allowed_mimes' => ['application/x-bittorrent'],
        'allowed_extensions' => ['torrent'],
    ],
    'nfo' => [
        'disk' => env('UPLOAD_NFO_DISK', 'nfo'),
        'directory' => env('UPLOAD_NFO_DIRECTORY', 'nfo'),
        'max_kilobytes' => (int) env('UPLOAD_NFO_MAX_KB', 256),
        'max_characters' => (int) env('UPLOAD_NFO_MAX_CHARACTERS', 262144),
        'allowed_mimes' => ['text/plain'],
        'allowed_extensions' => ['nfo', 'txt'],
    ],
    'images' => [
        'disk' => env('UPLOAD_IMAGES_DISK', 'images'),
        'directory' => env('UPLOAD_IMAGES_DIRECTORY', 'images'),
        'max_kilobytes' => (int) env('UPLOAD_IMAGES_MAX_KB', 5120),
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'max_width' => (int) env('UPLOAD_IMAGES_MAX_WIDTH', 2560),
        'max_height' => (int) env('UPLOAD_IMAGES_MAX_HEIGHT', 2560),
    ],
];
