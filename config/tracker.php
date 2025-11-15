<?php

declare(strict_types=1);

return [
    'announce_url' => env('TRACKER_ANNOUNCE_URL', 'https://nextgn.example/announce'),
    'additional_trackers' => [
        // 'https://backup-tracker.example/announce',
    ],
    'announce_min_interval_seconds' => (int) env('TRACKER_ANNOUNCE_MIN_INTERVAL', 30),
    'ghost_peer_timeout_minutes' => (int) env('TRACKER_GHOST_TIMEOUT_MINUTES', 45),
];
