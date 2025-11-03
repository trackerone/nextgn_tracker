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
        // Kør opstartslogik her hvis nødvendigt.
    }
}
