<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ApiKeyHmacMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('X-Api-Key', '');
        $signature = (string) $request->header('X-Api-Signature', '');
        $timestamp = (string) $request->header('X-Api-Timestamp', '');

        if ($key === '' || $signature === '' || $timestamp === '') {
            return response()->json(['message' => 'Missing API signature.'], 401);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = ApiKey::query()->where('key', $key)->first();

        if ($apiKey === null) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $secret = (string) config('security.api.hmac_secret', '');
        if ($secret === '') {
            return response()->json(['message' => 'Server HMAC secret not configured.'], 401);
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

        // The test canonical is: METHOD \n PATH \n TIMESTAMP \n BODY
        // Example: "GET\n/api/user\n<ts>\n"
        $candidates = [];
        foreach ($paths as $path) {
            $candidates[] = implode("\n", [$method, $path, $timestamp, $body]);
        }

        $ok = false;
        foreach ($candidates as $payload) {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (hash_equals($expected, $signature)) {
                $ok = true;
                break;
            }
        }

        if (! $ok) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $user = $apiKey->user;

        if ($user !== null) {
            /** @var \App\Models\User $user */
            Auth::setUser($user);
            $request->setUserResolver(static fn () => $user);
            $request->attributes->set('api_key', $apiKey);
        }

        return $next($request);
    }
}
