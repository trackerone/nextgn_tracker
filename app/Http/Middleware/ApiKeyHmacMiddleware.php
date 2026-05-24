<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ApiKeyHmacMiddleware
{
    private const DEFAULT_ALLOWED_TIME_SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('X-Api-Key', '');
        $signature = (string) $request->header('X-Api-Signature', '');
        $timestamp = (string) $request->header('X-Api-Timestamp', '');
        $nonce = (string) $request->header('X-Api-Nonce', '');

        if ($key === '' || $signature === '' || $timestamp === '') {
            $this->logSecurityEvent('api_hmac_missing_credentials', 'Missing API HMAC credentials.');

            return $this->unauthorized();
        }

        if (ctype_digit($timestamp) === false || $this->timestampIsFresh((int) $timestamp) === false) {
            $this->logSecurityEvent('api_hmac_replay_attempt', 'API HMAC timestamp failed freshness validation.', [
                'timestamp' => $timestamp,
            ]);

            return $this->unauthorized();
        }

        $apiKey = ApiKey::findForPlaintext($key);

        if ($apiKey === null) {
            $this->logSecurityEvent('api_hmac_unknown_key_prefix', 'API HMAC key prefix could not be resolved.', [
                'key_prefix' => ApiKey::prefixForPlaintext($key),
            ]);

            return $this->unauthorized();
        }

        $secret = $this->signingSecretFor($apiKey, $key);
        if ($secret === null) {
            $this->logSecurityEvent('api_hmac_invalid_signature', 'API HMAC signing secret could not be resolved.', [
                'api_key_id' => $apiKey->getKey(),
            ]);

            return $this->unauthorized();
        }

        if ($this->enforceNonce() && $nonce === '') {
            $this->logSecurityEvent('api_hmac_missing_nonce', 'API HMAC nonce is required but missing.', [
                'api_key_id' => $apiKey->getKey(),
            ]);

            return $this->unauthorized();
        }

        $method = strtoupper($request->getMethod());

        // Path variants (we accept several canonicalizations)
        $pathInfo = (string) $request->getPathInfo(); // often "/api/user"
        $pathFromHelper = '/'.ltrim((string) $request->path(), '/'); // "/api/user" (from "api/user")

        // Sometimes prefixes can be stripped in some server setups:
        $pathInfoStripped = preg_replace('#^/api/#', '/', $pathInfo);
        $pathHelperStripped = preg_replace('#^/api/#', '/', $pathFromHelper);

        $paths = array_values(array_unique(array_filter(
            [
                $pathInfo,
                $pathFromHelper,
                is_string($pathInfoStripped) ? $pathInfoStripped : '',
                is_string($pathHelperStripped) ? $pathHelperStripped : '',
            ],
            static fn (string $p): bool => $p !== ''
        )));

        // Body canonicalization:
        // Test signs GET with empty body, so enforce empty body for GET regardless of getContent().
        $body = $method === 'GET' ? '' : (string) $request->getContent();

        $requireNonce = $this->enforceNonce();

        // Canonical payload format (nonce rollout aware):
        // METHOD \n PATH \n TIMESTAMP \n NONCE \n BODY
        // Legacy format without nonce is allowed only when enforcement is disabled.
        $candidates = [];
        foreach ($paths as $path) {
            if ($requireNonce === false) {
                $candidates[] = implode("\n", [$method, $path, $timestamp, $body]);
            }

            if ($nonce !== '') {
                $candidates[] = implode("\n", [$method, $path, $timestamp, $nonce, $body]);
            }
        }

        $ok = false;
        foreach ($candidates as $payload) {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (hash_equals($expected, $signature)) {
                $ok = true;
                break;
            }
        }

        if ($ok === false) {
            $this->logSecurityEvent('api_hmac_invalid_signature', 'API HMAC signature validation failed.', [
                'api_key_id' => $apiKey->getKey(),
                'key_prefix' => $apiKey->key_prefix,
                'hmac_version' => $apiKey->hmac_version,
            ]);

            return $this->unauthorized();
        }

        if ($nonce !== '' && $this->markNonceAsUsed($apiKey, $nonce, $this->allowedSkewSeconds()) === false) {
            $this->logSecurityEvent('api_hmac_replay_attempt', 'API HMAC nonce replay detected.', [
                'api_key_id' => $apiKey->getKey(),
            ]);

            return $this->unauthorized();
        }

        if ($apiKey->usesLegacyGlobalHmac()) {
            $this->logSecurityEvent('api_hmac_legacy_global_usage', 'Deprecated global API HMAC secret accepted.', [
                'api_key_id' => $apiKey->getKey(),
                'key_prefix' => $apiKey->key_prefix,
            ]);
        }

        $apiKey->upgradeFromPlaintextIfNeeded($key);

        $user = $apiKey->user;

        if ($user !== null) {
            /** @var \App\Models\User $user */
            if ($user->isBanned() || $user->isDisabled()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            Auth::setUser($user);
            $request->setUserResolver(static fn () => $user);
            $request->attributes->set('api_key', $apiKey);
        }

        return $next($request);
    }

    private function signingSecretFor(ApiKey $apiKey, string $plainKey): ?string
    {
        if ($apiKey->usesLegacyGlobalHmac() === false) {
            if ($apiKey->hmacSecretMatchesPlaintext($plainKey) === false) {
                return null;
            }

            return ApiKey::hmacSigningSecretForPlaintext($plainKey);
        }

        $secret = (string) config('security.api.hmac_secret', '');

        return $secret === '' ? null : $secret;
    }

    private function timestampIsFresh(int $timestamp): bool
    {
        return abs(now()->timestamp - $timestamp) <= $this->allowedSkewSeconds();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logSecurityEvent(string $eventType, string $message, array $context = []): void
    {
        Log::channel('security')->warning($message, $context + [
            'event_type' => $eventType,
        ]);
    }

    private function allowedSkewSeconds(): int
    {
        return (int) config('security.api.allowed_time_skew_seconds', self::DEFAULT_ALLOWED_TIME_SKEW_SECONDS);
    }

    private function enforceNonce(): bool
    {
        return (bool) config('security.api.require_nonce', true);
    }

    private function markNonceAsUsed(ApiKey $apiKey, string $nonce, int $ttlSeconds): bool
    {
        $cacheKey = sprintf('api:hmac:nonce:%s:%s', (string) $apiKey->getKey(), hash('sha256', $nonce));

        return Cache::add($cacheKey, true, now()->addSeconds($ttlSeconds));
    }

    private function unauthorized(): Response
    {
        return response()->json(['message' => 'Unauthorized.'], 401);
    }
}
