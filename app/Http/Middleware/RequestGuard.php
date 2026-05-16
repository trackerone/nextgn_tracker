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
        'javascript\\s*:',
        'data\\s*:\\s*(?:text|application)/(?:html|javascript)\\s*;base64',
        '"__proto__"\\s*:',
        '(\\{|\\[)\\s*\\"(?:__proto__|constructor)\\"',
    ];

    private const SENSITIVE_KEY_TERMS = [
        'password',
        'token',
        'secret',
        'api_key',
        'key',
        'credential',
        'invite',
        'invite_code',
        'passkey',
    ];

    public function __construct(
        private readonly SanitizationService $sanitizer,
    ) {}

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
     * @param  array<int, string>  $keyPath
     * @return array{0: array<mixed>, 1: array<int, array<string, bool|int|string>>}
     */
    private function sanitizePayload(array $payload, array $keyPath = [], bool $sensitiveAncestor = false): array
    {
        $sanitized = [];
        $incidents = [];

        foreach ($payload as $key => $value) {
            $currentKey = (string) $key;
            $currentPath = [...$keyPath, $currentKey];
            $isSensitive = $sensitiveAncestor || $this->isSensitiveKey($currentKey);

            if ($value instanceof UploadedFile) {
                $sanitized[$key] = $value;

                continue;
            }

            if (is_array($value)) {
                [$childSanitized, $childIncidents] = $this->sanitizePayload($value, $currentPath, $isSensitive);

                $sanitized[$key] = $childSanitized;
                $incidents = array_merge($incidents, $childIncidents);

                continue;
            }

            if (is_string($value)) {

                $cleanValue = $this->sanitizer->sanitizeString($value);

                // Block clearly malicious protocol payloads
                if ($this->containsMaliciousPayload($value)) {
                    $incidents[] = $this->incidentForValue($currentPath, $value, $isSensitive);
                }

                $sanitized[$key] = $cleanValue;

                continue;
            }

            $sanitized[$key] = $value;
        }

        return [$sanitized, $incidents];
    }

    private function containsMaliciousPayload(string $value): bool
    {
        foreach (self::MALICIOUS_PATTERNS as $pattern) {
            if (preg_match('~'.$pattern.'~i', $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $keyPath
     * @return array<string, bool|int|string>
     */
    private function incidentForValue(array $keyPath, string $value, bool $isSensitive): array
    {
        $incident = [
            'key' => implode('.', $keyPath),
        ];

        if (! $isSensitive) {
            $incident['value'] = $this->truncateForLog($value);

            return $incident;
        }

        $incident['value'] = '[REDACTED]';
        $incident['redacted'] = true;
        $incident['fingerprint'] = 'sha256:'.hash('sha256', $value);
        $incident['length'] = strlen($value);

        return $incident;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = Str::lower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEY_TERMS as $term) {
            if (str_contains($normalizedKey, $term)) {
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
     * @param  array<int, array<string, bool|int|string>>  $incidents
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
