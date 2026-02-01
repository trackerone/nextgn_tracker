<?php

declare(strict_types=1);

namespace App\Http\Middleware\Tracker;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pass-through middleware for announce.
 *
 * AnnounceController is the single source of truth for:
 * - passkey validation + exact failure reason strings
 * - SecurityEvent logging
 * - client ban checks
 * - rate limit logging behavior (HTTP 200 + logged event)
 *
 * Any short-circuiting here will break tests.
 */
final class PassThroughAnnounceValidation
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
