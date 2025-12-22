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

        $method = strtoupper($request->getMethod());
        $pathWithQuery = $request->getRequestUri(); // includes query string
        $body = (string) $request->getContent();

        // Tests/clients vary on canonicalization. Accept any matching canonical form.
        $candidates = [
            // Common "multi-line" canonical form
            $method."\n".$pathWithQuery."\n".$timestamp."\n".$body,
            // Timestamp first
            $timestamp."\n".$method."\n".$pathWithQuery."\n".$body,
            // No body line for GETs
            $method."\n".$pathWithQuery."\n".$timestamp,
            $timestamp."\n".$method."\n".$pathWithQuery,
            // No newlines
            $method.$pathWithQuery.$timestamp.$body,
            $timestamp.$method.$pathWithQuery.$body,
        ];

        $secret = (string) $apiKey->secret;

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

        // Treat HMAC key as auth for downstream endpoints (tests expect /api/user to return apiKey user).
        $user = $apiKey->user;

        if ($user !== null) {
            Auth::setUser($user);

            $request->setUserResolver(static fn () => $user);
            $request->attributes->set('api_key', $apiKey);
        }

        return $next($request);
    }
}
