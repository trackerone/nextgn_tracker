<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\SecurityAuditLog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiKeyHmacMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('security.api.hmac_secret');

        if ($secret === '') {
            abort(500, 'API HMAC not configured');
        }

        $apiKeyValue = $request->header('X-Api-Key');
        $timestampHeader = $request->header('X-Api-Timestamp');
        $signature = $request->header('X-Api-Signature');

        if ($apiKeyValue === null || $timestampHeader === null || $signature === null) {
            $this->logFailure($request, 'missing_headers');
            abort(401, 'Invalid API signature.');
        }

        $timestamp = $this->normalizeTimestamp($timestampHeader);

        if ($timestamp === null) {
            $this->logFailure($request, 'timestamp_format');
            abort(401, 'Invalid API timestamp.');
        }

        $allowedSkew = (int) config('security.api.allowed_time_skew_seconds', 300);
        $now = now()->getTimestamp();

        if (abs($now - $timestamp) > $allowedSkew) {
            $this->logFailure($request, 'timestamp_skew');
            abort(401, 'API timestamp out of range.');
        }

        $apiKey = ApiKey::query()
            ->where('key', $apiKeyValue)
            ->with('user')
            ->first();

        if ($apiKey === null || $apiKey->user === null) {
            $this->logFailure($request, 'invalid_key');
            abort(401, 'Invalid API key.');
        }

        $canonical = $this->buildCanonicalString($request, $timestampHeader);
        $computedSignature = hash_hmac('sha256', $canonical, $secret);

        if (! hash_equals($computedSignature, (string) $signature)) {
            $this->logFailure($request, 'invalid_signature');
            abort(401, 'Invalid API signature.');
        }

        if (! auth()->check()) {
            auth()->setUser($apiKey->user);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        SecurityAuditLog::log($apiKey->user, 'api.request', [
            'user_id' => $apiKey->user_id,
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }

    private function normalizeTimestamp(string $value): ?int
    {
        if (ctype_digit($value)) {
            return (int) $value;
        }

        try {
            return CarbonImmutable::parse($value)->getTimestamp();
        } catch (Throwable) {
            return null;
        }
    }

    private function buildCanonicalString(Request $request, string $timestampHeader): string
    {
        $method = strtoupper($request->method());
        $path = $request->getPathInfo();
        $body = (string) $request->getContent();

        return implode("\n", [$method, $path, $timestampHeader, $body]);
    }

    private function logFailure(Request $request, string $reason): void
    {
        SecurityAuditLog::log(null, 'api.request.failed', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'reason' => $reason,
        ]);
    }
}
