<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Public shallow readiness only; never expose hosts, schema, credentials or exceptions.
        try {
            DB::select('SELECT 1 AS probe');
            $cache = Cache::store();
            $key = 'nextgn:readiness:'.bin2hex(random_bytes(6));
            $cache->put($key, 'ok', 15);
            $ok = $cache->get($key) === 'ok';
            $cache->forget($key);

            if (! $ok) {
                return $this->unavailable();
            }

            return new JsonResponse(['status' => 'ok'], 200, ['Cache-Control' => 'no-store']);
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    private function unavailable(): JsonResponse
    {
        return new JsonResponse(['status' => 'degraded'], 503, ['Cache-Control' => 'no-store']);
    }
}
