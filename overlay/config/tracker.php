<?php

declare(strict_types=1);

return [
    'mode' => env('TRACKER_MODE', 'embedded'),
    'external_announce' => env('EXTERNAL_ANNOUNCE_URL', ''),
];
