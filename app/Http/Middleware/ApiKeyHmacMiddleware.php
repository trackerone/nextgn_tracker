<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\SecurityAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyHmacMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('security.api.hmac_secret') ?? config('api.hmac_secret');
        abort_if(empty($secret), 500, 'API HMAC not configured');

        $headers = ['key' => $request->header('X-Api-Key'), 'timestamp' => $request->header('X-Api-Timestamp'), 'signature' => $request->header('X-Api-Signature')];
        if (in_array(null, $headers, true)) {
            $this->reject($request, 'missing_headers');
        }

        $timestamp = ctype_digit($headers['timestamp']) ? (int) $headers['timestamp'] : (($parsed = strtotime($headers['timestamp'])) === false ? null : $parsed);
        if ($timestamp === null) {
            $this->reject($request, 'invalid_timestamp');
        }

        if (abs(time() - $timestamp) > (int) config('security.api.allowed_time_skew_seconds', 300)) {
            $this->reject($request, 'timestamp_skew');
        }

        $apiKey = ApiKey::query()->where('key', $headers['key'])->first();
        if ($apiKey === null) {
            $this->reject($request, 'unknown_key');
        }

        $canonical = strtoupper($request->getMethod())."\n".$request->getPathInfo()."\n".$headers['timestamp']."\n".$request->getContent();
        if (! hash_equals(hash_hmac('sha256', $canonical, $secret), $headers['signature'])) {
            $this->reject($request, 'invalid_signature');
        }

        $user = $apiKey->user;
        if ($user !== null && auth()->user() === null) {
            auth()->setUser($user);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        SecurityAuditLog::log($user, 'api.request', ['ip' => $request->ip(), 'path' => $request->path(), 'method' => $request->method()]);

        return $next($request);
    }

    private function reject(Request $request, string $reason): never
    {
        SecurityAuditLog::log(null, 'api.request.failed', ['ip' => $request->ip(), 'path' => $request->path(), 'reason' => $reason]);
        abort(401, 'Invalid API signature.');
    }
}
