<?php

declare(strict_types=1);

$trustedProxies = explode(',', (string) env(
    'TRUSTED_PROXIES',
    '127.0.0.1,::1,172.16.0.0/12'
));

return [
    'trusted' => array_values(array_filter(array_map('trim', $trustedProxies))),
];
