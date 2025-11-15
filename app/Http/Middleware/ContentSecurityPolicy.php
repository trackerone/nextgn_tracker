<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContentSecurityPolicy
{
    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $scriptSources = ["'self'", "'unsafe-inline'"];
        $styleSources = ["'self'", "'unsafe-inline'"];
        $connectSources = ["'self'"];
        $fontSources = ["'self'", 'data:'];

        if (app()->isLocal()) {
            $devServerHttpOrigins = [
                'http://localhost:5173',
                'http://127.0.0.1:5173',
            ];

            $scriptSources = array_merge($scriptSources, $devServerHttpOrigins);
            $styleSources = array_merge($styleSources, $devServerHttpOrigins);
            $fontSources = array_merge($fontSources, $devServerHttpOrigins);
            $connectSources = array_merge(
                $connectSources,
                $devServerHttpOrigins,
                [
                    'ws://localhost:5173',
                    'ws://127.0.0.1:5173',
                ],
            );
        }

        $policy = implode('; ', [
            "default-src 'self'",
            "img-src 'self' data:",
            sprintf('style-src %s', implode(' ', $styleSources)),
            sprintf('script-src %s', implode(' ', $scriptSources)),
            sprintf('connect-src %s', implode(' ', $connectSources)),
            sprintf('font-src %s', implode(' ', $fontSources)),
        ]);

        $response->headers->set('Content-Security-Policy', $policy);

        return $response;
    }
}
