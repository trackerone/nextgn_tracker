<?php

declare(strict_types=1);

return [
    'max_per_page' => 100,
    'default_per_page' => 25,
    'allowed_sort_fields' => [
        'uploaded_at',
        'size_bytes',
        'seeders',
        'leechers',
        'completed',
        'name',
    ],
    'order_aliases' => [
        'created' => 'uploaded_at',
        'size' => 'size_bytes',
        'seeders' => 'seeders',
        'leechers' => 'leechers',
        'completed' => 'completed',
    ],
];
