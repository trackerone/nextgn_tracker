<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateAnnounceRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        // Minimal baseline – vi strammer når tests kræver det.
        // Sørg for at den altid returnerer et Response og ikke kaster
        // før vi har implementeret de præcise announce-krav.
        return $next($request);
    }
}
