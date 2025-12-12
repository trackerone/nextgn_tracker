<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateAnnounceRequest
{
    /**
     * Handle an incoming announce request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // For now, we keep this middleware as a no-op so the tracker
        // controller can handle validation and error responses.
        return $next($request);
    }
}
