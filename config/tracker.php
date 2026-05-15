<?php

declare(strict_types=1);

return [
    'announce_url' => env('TRACKER_ANNOUNCE_URL', 'https://nextgn.example/announce'),
    'additional_trackers' => [
        // 'https://backup-tracker.example/announce',
    ],
    'announce_min_interval_seconds' => (int) env('TRACKER_ANNOUNCE_MIN_INTERVAL', 30),
    'ghost_peer_timeout_minutes' => (int) env('TRACKER_GHOST_TIMEOUT_MINUTES', 45),
    'credit' => [
        'max_upload_bytes_per_second' => (int) env('TRACKER_MAX_UPLOAD_BYTES_PER_SECOND', 1_073_741_824),
        'max_download_bytes_per_second' => (int) env('TRACKER_MAX_DOWNLOAD_BYTES_PER_SECOND', 1_073_741_824),
        'max_upload_bytes_per_announce' => (int) env('TRACKER_MAX_UPLOAD_BYTES_PER_ANNOUNCE', 1_099_511_627_776),
        'max_download_bytes_per_announce' => (int) env('TRACKER_MAX_DOWNLOAD_BYTES_PER_ANNOUNCE', 1_099_511_627_776),
        'max_upload_torrent_size_multiplier' => (int) env('TRACKER_MAX_UPLOAD_TORRENT_SIZE_MULTIPLIER', 100),
        'max_download_torrent_size_multiplier' => (int) env('TRACKER_MAX_DOWNLOAD_TORRENT_SIZE_MULTIPLIER', 1),
    ],
];
