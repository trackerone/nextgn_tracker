<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OperationsProbe
{
    public function __construct(private readonly OperationsTelemetry $telemetry) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $checks = [];
        $dbMs = null;

        try {
            $started = microtime(true);
            DB::select('SELECT 1 AS probe');
            $dbMs = (int) round((microtime(true) - $started) * 1000);
            $checks[] = $this->check('database', 'Database', 'healthy', 'Query succeeded in '.$dbMs.' ms.');
        } catch (Throwable) {
            $checks[] = $this->check('database', 'Database', 'critical', 'Connection or query failed.');
        }

        $driver = (string) config('cache.default', 'unknown');
        try {
            $key = 'nextgn:ops:probe:'.bin2hex(random_bytes(6));
            $cache = Cache::store();
            $cache->put($key, 'verified', 20);
            if ($cache->get($key) !== 'verified') {
                throw new \RuntimeException('Cache readback failed');
            }
            $cache->forget($key);
            $checks[] = $this->check('cache', 'Cache', 'healthy', $driver.' driver: read/write verified.');
        } catch (Throwable) {
            $checks[] = $this->check('cache', 'Cache', 'degraded', $driver.' driver: read/write failed.');
        }

        $diskFree = null;
        $diskTotal = null;
        try {
            $directory = storage_path('app');
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new \RuntimeException('Storage directory unavailable');
            }
            $probeFile = tempnam($directory, 'ops-');
            if ($probeFile === false) {
                throw new \RuntimeException('Storage write failed');
            }
            try {
                if (file_put_contents($probeFile, 'ok') === false || file_get_contents($probeFile) !== 'ok') {
                    throw new \RuntimeException('Storage readback failed');
                }
            } finally {
                @unlink($probeFile);
            }

            $free = disk_free_space($directory);
            $total = disk_total_space($directory);
            $diskFree = $free === false ? null : max(0, (int) $free);
            $diskTotal = $total === false ? null : max(0, (int) $total);

            $percent = $diskFree !== null && $diskTotal !== null && $diskTotal > 0
                ? (int) floor(100 * $diskFree / $diskTotal)
                : null;
            $status = $percent === null ? 'unknown' : ($percent < 3 ? 'critical' : ($percent < 10 ? 'degraded' : 'healthy'));
            $checks[] = $this->check(
                'storage',
                'Storage',
                $status,
                $percent === null ? 'Read/write verified; free space unavailable.' : 'Read/write verified; '.$percent.'% disk free.'
            );
        } catch (Throwable) {
            $checks[] = $this->check('storage', 'Storage', 'critical', 'Storage read/write could not be verified.');
        }

        $scheduler = $this->telemetry->lastSuccess('scheduler.tick');
        $schedulerState = $scheduler === null ? 'unknown' : ($scheduler->lt(now()->subMinutes(4)) ? 'critical' : 'healthy');
        $checks[] = $this->check(
            'scheduler',
            'Scheduler',
            $schedulerState,
            $scheduler === null ? 'No scheduler heartbeat observed yet.' : 'Last heartbeat: '.$scheduler->toDateTimeString()
        );

        $queueDriver = (string) config('queue.default', 'sync');
        if ($queueDriver === 'sync') {
            $checks[] = $this->check('queue', 'Queue', 'not_applicable', 'sync driver: jobs run inline; no worker required.');
        } else {
            $checks[] = $this->check('queue', 'Queue', 'unknown', $queueDriver.' driver: worker heartbeat not yet instrumented.');
        }

        $counters = $this->telemetry->counters(15);
        $announceOk = $counters['announce_ok'];
        $announceRejected = $counters['announce_rejected'];
        if ($announceOk === null || $announceRejected === null) {
            $checks[] = $this->check('tracker', 'Tracker', 'unknown', 'No tracker traffic recorded in the last 15 minutes.');
        } else {
            $total = $announceOk + $announceRejected;
            $bad = $total >= 10 && $announceRejected / $total >= 0.3;
            $checks[] = $this->check(
                'tracker', 'Tracker', $bad ? 'degraded' : 'healthy',
                sprintf('Announces in 15m: %d accepted, %d rejected.', $announceOk, $announceRejected)
            );
        }

        $http5xx = $counters['http_5xx'];
        $httpOk = $counters['http_ok'];
        if ($http5xx === null || $httpOk === null) {
            $checks[] = $this->check('http', 'Web requests', 'unknown', 'No HTTP telemetry captured in the last 15 minutes.');
        } else {
            $total = $http5xx + $httpOk;
            $bad = $total >= 20 && $http5xx / $total >= 0.05;
            $checks[] = $this->check('http', 'Web requests', $bad ? 'degraded' : 'healthy', $http5xx.' HTTP 5xx failures / '.$total.' requests (15m).');
        }

        $checks[] = $this->check('backups', 'Backups', 'unknown', 'Backup and restore verification is not configured on this host.');

        return [
            'checks' => $checks,
            'overall' => $this->overall($checks),
            'checked_at' => now()->toDateTimeString(),
            'database_latency_ms' => $dbMs,
            'disk_free_bytes' => $diskFree,
            'disk_total_bytes' => $diskTotal,
            'counters' => $counters,
            'jobs' => $this->telemetry->jobs(),
            'version' => $this->version(),
            'php_version' => PHP_VERSION,
            'host_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : false,
        ];
    }

    /**
     * @return array{id:string,label:string,status:string,details:string}
     */
    private function check(string $id, string $label, string $status, string $details): array
    {
        return compact('id', 'label', 'status', 'details');
    }

    /**
     * @param  array<int, array{id:string,label:string,status:string,details:string}>  $checks
     */
    private function overall(array $checks): string
    {
        foreach (['critical', 'degraded', 'unknown'] as $severity) {
            foreach ($checks as $check) {
                if ($check['status'] === $severity) {
                    return $severity;
                }
            }
        }

        return 'healthy';
    }

    private function version(): string
    {
        $configured = (string) (config('app.version') ?: '');
        if ($configured !== '' && $configured !== 'unknown') {
            return $configured;
        }

        $headFile = base_path('.git/HEAD');
        if (! is_readable($headFile)) {
            return 'unknown (set APP_VERSION at deployment)';
        }

        $head = trim((string) @file_get_contents($headFile));
        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            if (preg_match('~\Arefs/(?:heads|tags)/[A-Za-z0-9._/-]+\z~', $ref) !== 1 || str_contains($ref, '..')) {
                return 'unknown';
            }
            $head = trim((string) @file_get_contents(base_path('.git/'.$ref)));
        }

        return preg_match('/\A[a-f0-9]{40}\z/i', $head) === 1 ? substr($head, 0, 12) : 'unknown';
    }
}
