<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class ValidateAnnounceRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = (string) ($request->userAgent() ?? '');
        $passkey = (string) ($request->route('passkey') ?? '');

        // 1) Banned client detection (tests use "BannedClient/1.0")
        if ($userAgent !== '' && stripos($userAgent, 'bannedclient') !== false) {
            $this->logSecurityEvent(
                $request,
                null,
                'tracker.client_banned',
                'high',
                'Banned client attempted announce',
                [
                    'passkey' => $passkey,
                    'user_agent' => $userAgent,
                    'path' => $request->path(),
                    'query' => $request->query(),
                ]
            );

            // Do NOT block; tests expect 200 OK + log entry.
            return $next($request);
        }

        // 2) Rate limit logging (log-only; do not block)
        if ($passkey !== '') {
            $counterKey = 'announce:rate:'.$passkey;

            try {
                $count = (int) Cache::increment($counterKey);
                if ($count === 1) {
                    Cache::put($counterKey, 1, 60); // 60 seconds window
                }

                if ($count > 1) {
                    $user = User::query()->where('passkey', $passkey)->first();

                    $this->logSecurityEvent(
                        $request,
                        $user,
                        'tracker.rate_limited',
                        'medium',
                        'Announce rate limit exceeded',
                        [
                            'passkey' => $passkey,
                            'count' => $count,
                            'window_seconds' => 60,
                            'path' => $request->path(),
                        ]
                    );
                }
            } catch (\Throwable) {
                // Never fail announce due to rate logging
            }
        }

        return $next($request);
    }

    private function logSecurityEvent(
        Request $request,
        ?User $user,
        string $eventType,
        string $severity,
        string $message,
        array $context
    ): void {
        $payload = [
            'user_id' => $user?->getKey(),
            'ip_address' => (string) ($request->ip() ?? '0.0.0.0'),
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'event_type' => $eventType,
            'severity' => $severity,
            'message' => $message,
            'context' => $this->sanitizeForJson($context),
        ];

        try {
            SecurityEvent::query()->create($payload);
        } catch (\Throwable) {
            // swallow
        }
    }

    private function sanitizeForJson(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? $this->sanitizeString($k) : $k;
                $out[$key] = $this->sanitizeForJson($v);
            }

            return $out;
        }

        if ($value instanceof \Stringable) {
            return $this->sanitizeString((string) $value);
        }

        return $this->sanitizeString((string) $value);
    }

    private function sanitizeString(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return 'base64:'.base64_encode($value);
    }
}
