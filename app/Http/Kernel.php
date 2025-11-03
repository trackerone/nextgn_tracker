<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // Brug standarden – Laravel 11 autokonfigurerer en del.
    // Hvis du har brug for ekstra global middleware eller grupper,
    // gør det via bootstrap/app.php ->withMiddleware(...)
}
