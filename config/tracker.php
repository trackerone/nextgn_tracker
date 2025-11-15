<?php

declare(strict_types=1);

return [
    'announce_url' => env(
        'TRACKER_ANNOUNCE_URL',
        rtrim((string) env('APP_URL', 'http://localhost'), '/').'/announce/%s'
    ),
];
