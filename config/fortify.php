<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'username' => 'email',
    'email' => 'email',
    'views' => false,
    'home' => '/home',
    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]),
    ],
];
