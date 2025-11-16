<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\SanitizationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ResponseGuard
{
    public function __construct(
        private readonly SanitizationService $sanitizer,
    )
    {
    }

    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldBypass($request, $response)) {
            return $response;
        }

        $this->applySecurityHeaders($response);
        $this->sanitizeBody($response);

        return $response;
    }

    private function shouldBypass(Request $request, Response $response): bool
    {
        if ($response instanceof JsonResponse || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return true;
        }

        $contentType = $response->headers->get('Content-Type', '');

        if (is_string($contentType) && str_contains(strtolower($contentType), 'application/json')) {
            return true;
        }

        return $request->expectsJson();
    }

    private function applySecurityHeaders(Response $response): void
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
    }

    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ]);
    }

    private function sanitizeBody(Response $response): void
    {
        $contentType = $response->headers->get('Content-Type', '');

        if (! is_string($contentType) || ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'text/plain'))) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === null) {
            return;
        }

        $content = $this->stripInlineScripts($content);
        $content = $this->sanitizer->sanitizeHtmlDocument($content);

        $response->setContent($content);
    }

    private function stripInlineScripts(string $content): string
    {
        return preg_replace_callback(
            '/<script\b([^>]*)>(.*?)<\/script>/is',
            function (array $matches): string {
                if (preg_match('/\ssrc\s*=/i', $matches[1])) {
                    return $matches[0];
                }

                return '';
            },
            $content,
        ) ?? $content;
    }
}
