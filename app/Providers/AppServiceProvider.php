<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrér bindings her hvis du får brug for det.
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            config(['app.debug' => false]);
        }

        config([
            'session.secure' => config('session.secure', app()->environment('production')),
            'session.http_only' => config('session.http_only', true),
            'session.same_site' => config('session.same_site', 'strict'),
        ]);
    }
}
