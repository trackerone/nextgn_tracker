<?php

declare(strict_types=1);

namespace App\Support\Security;

use Illuminate\Http\Request;

final class LoginThrottleKey
{
    public static function from(string $email, string $ip): string
    {
        return sprintf('login:%s|%s', mb_strtolower($email), $ip);
    }

    public static function fromRequest(Request $request): string
    {
        return self::from(
            (string) $request->input('email', ''),
            (string) $request->ip()
        );
    }

    public static function throttleMiddlewareKey(string $email, string $ip): string
    {
        return self::middlewareKey('login', self::from($email, $ip));
    }

    /**
     * @return list<string>
     */
    public static function keysForClearing(string $email, string $ip): array
    {
        return [
            self::from($email, $ip),
            self::throttleMiddlewareKey($email, $ip),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keysForClearingRequest(Request $request): array
    {
        return self::keysForClearing(
            (string) $request->input('email', ''),
            (string) $request->ip()
        );
    }

    /**
     * Match Laravel's named throttle middleware key for hashed limiter keys.
     */
    private static function middlewareKey(string $limiterName, string $key): string
    {
        return md5($limiterName.$key);
    }
}
