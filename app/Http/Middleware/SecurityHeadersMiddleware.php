<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityHeadersMiddleware
{
    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (is_string($contentType) && str_contains(strtolower($contentType), 'application/json')) {
            return $response;
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->isJson()) {
            return $response;
        }

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline'",
        ];

        foreach ($headers as $header => $value) {
            if (! $response->headers->has($header)) {
                $response->headers->set($header, $value, true);
            }
        }

        return $response;
    }
}
