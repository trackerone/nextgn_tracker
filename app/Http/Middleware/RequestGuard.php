<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\SanitizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestGuard
{
    private const MALICIOUS_PATTERNS = [
        '(?:%3C|<)script',
        '\\s<script',
        'javascript\\s*:',
        'data\\s*:\\s*(?:text|application)/(?:html|javascript)\\s*;base64',
        'PHNjcmlwdD4',
        '\\"__proto__\\"\\s*:',
        '(\\{|\\[)\\s*\\"(?:__proto__|constructor)\\"',
    ];

    public function __construct(
        private readonly SanitizationService $sanitizer,
    ) {
    }

    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        [$sanitized, $incidents] = $this->sanitizePayload($request->all());

        if ($incidents !== []) {
            $this->logIncident($request, $incidents);

            abort(Response::HTTP_BAD_REQUEST, 'Malicious payload detected.');
        }

        $request->merge($sanitized);

        return $next($request);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array{0: array<mixed>, 1: array<int, array<string, string>>}
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];
        $incidents = [];

        foreach ($payload as $key => $value) {
            if ($value instanceof UploadedFile) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                [$childSanitized, $childIncidents] = $this->sanitizePayload($value);
                $sanitized[$key] = $childSanitized;
                $incidents = array_merge($incidents, $childIncidents);
                continue;
            }

            if (is_string($value)) {
                if ($this->containsMaliciousPayload($value)) {
                    $incidents[] = [
                        'key' => (string) $key,
                        'value' => $this->truncateForLog($value),
                    ];
                }

                $sanitized[$key] = $this->sanitizer->sanitizeString($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return [$sanitized, $incidents];
    }

    private function containsMaliciousPayload(string $value): bool
    {
        foreach (self::MALICIOUS_PATTERNS as $pattern) {
            if (preg_match('/'.$pattern.'/i', $value)) {
                return true;
            }
        }

        return false;
    }

    private function truncateForLog(string $value): string
    {
        return Str::limit($value, 120);
    }

    /**
     * @param  array<int, array<string, string>>  $incidents
     */
    private function logIncident(Request $request, array $incidents): void
    {
        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/security.log'),
            'level' => 'warning',
        ])->warning('RequestGuard blocked payload', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'incidents' => $incidents,
        ]);
    }
}
