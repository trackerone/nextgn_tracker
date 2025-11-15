<?php

declare(strict_types=1);

return [
    'announce_url' => env('TRACKER_ANNOUNCE_URL', 'https://nextgn.example/announce'),
    'additional_trackers' => [
        // 'https://backup-tracker.example/announce',
    ],
];
