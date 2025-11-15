<?php

declare(strict_types=1);

return [
    'allowed_clients' => [
        '/^uTorrent/i',
        '/^qBittorrent/i',
        '/^Transmission/i',
        '/^Deluge/i',
    ],
    'banned_clients' => [
        '/^FakeClient/i',
        '/^Transmission\\/0\\.7/i',
    ],
    'min_client_version' => [
        'qBittorrent' => '4.4.0',
        'uTorrent' => '3.5.0',
    ],
];
