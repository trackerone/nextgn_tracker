<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\OperationsQueueHeartbeatJob;
use App\Services\Operations\OperationsAlerts;
use App\Services\Operations\OperationsProbe;
use App\Services\Operations\OperationsTelemetry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MonitorOperationsCommand extends Command
{
    protected $signature = 'ops:monitor';

    protected $description = 'Capture a bounded platform health snapshot and reconcile Sysop incidents.';

    public function handle(
        OperationsProbe $probe,
        OperationsAlerts $alerts,
        OperationsTelemetry $telemetry
    ): int {
        $started = microtime(true);
        $telemetry->job('ops:monitor', 'started');
        $status = $probe->collect();
        $alerts->reconcile($status['checks']);

        if ((string) config('queue.default', 'sync') !== 'sync') {
            try {
                $telemetry->job('queue.heartbeat.requested', 'started');
                OperationsQueueHeartbeatJob::dispatch();
            } catch (Throwable) {
                $telemetry->job('queue.heartbeat.requested', 'failed', null, 'Unable to dispatch worker heartbeat');
            }
        }

        $seeders = null;
        $leechers = null;

        try {
            // Deliberately periodic, never executed in the announce/scrape hot path.
            $seeders = (int) DB::table('torrents')->sum('seeders');
            $leechers = (int) DB::table('torrents')->sum('leechers');
        } catch (Throwable) {
            // Some environments have not yet migrated torrent data.
        }

        try {
            DB::table('ops_snapshots')->insert([
                'captured_at' => now(),
                'overall_status' => $status['overall'],
                'tracker_announces' => $status['counters']['announce_ok'],
                'tracker_rejected' => $status['counters']['announce_rejected'],
                'tracker_scrapes' => $status['counters']['scrape_ok'],
                'http_5xx' => $status['counters']['http_5xx'],
                'disk_free_bytes' => $status['disk_free_bytes'],
                'database_latency_ms' => $status['database_latency_ms'],
                'seeders' => $seeders,
                'leechers' => $leechers,
                'host_memory_total_bytes' => $status['host']['memory_total_bytes'],
                'host_memory_available_bytes' => $status['host']['memory_available_bytes'],
                'cpu_load_1m' => $status['host']['load_1m'],
            ]);

            // Indexed time-range cleanup; keep 30 days of compact snapshots.
            DB::table('ops_snapshots')->where('captured_at', '<', now()->subDays(30))->delete();
        } catch (Throwable) {
            $telemetry->job('ops:monitor', 'failed', null, 'Snapshot storage unavailable');

            return self::FAILURE;
        }

        $telemetry->job('ops:monitor', 'succeeded', (int) round((microtime(true) - $started) * 1000));
        $this->info('Platform snapshot captured; overall status: '.$status['overall']);

        return self::SUCCESS;
    }
}
