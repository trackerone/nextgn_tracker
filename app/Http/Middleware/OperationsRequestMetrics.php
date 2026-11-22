<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Operations\OperationsTelemetry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperationsRequestMetrics
{
    public function __construct(private readonly OperationsTelemetry $telemetry) {}

    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // No raw paths, passkeys, user identifiers or query strings.
        if (! $request->is('health', 'sysop/*', 'announce/*', 'scrape/*')) {
            $failed = $response->getStatusCode() >= 500;
            $this->telemetry->count($failed ? 'http_5xx' : 'http_ok');

            if ($request->routeIs('torrents.index')) {
                $this->telemetry->count($failed ? 'browse_error' : 'browse_ok');
            }

            if ($request->routeIs('rss.*') || $request->is('rss/*')) {
                $this->telemetry->count($failed ? 'rss_error' : 'rss_ok');
            }
        }

        return $response;
    }
}
