<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsAlerts;
use App\Services\Operations\OperationsProbe;
use Illuminate\Http\JsonResponse;

final class OperationsSupportBundleController extends Controller
{
    public function __invoke(OperationsProbe $probe, OperationsAlerts $alerts): JsonResponse
    {
        $status = $probe->collect();

        // No raw log entries, request URLs, IPs, credentials, host paths or backups.
        $jobs = [];
        foreach ($status['jobs'] as $key => $job) {
            $jobs[] = [
                'job' => $key,
                'state' => $job['state'] ?? 'unknown',
                'last_success_at' => $job['last_success_at'] ?? null,
                'last_failed_at' => $job['last_failed_at'] ?? null,
            ];
        }

        $incidents = [];
        foreach ($alerts->recent(50) as $incident) {
            $incidents[] = [
                'component' => $incident->component,
                'severity' => $incident->severity,
                'status' => $incident->status,
                'first_seen_at' => $incident->first_seen_at,
                'last_seen_at' => $incident->last_seen_at,
                'occurrences' => $incident->occurrences,
            ];
        }

        return new JsonResponse([
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'release' => $status['version'],
            'overall_status' => $status['overall'],
            'checks' => $status['checks'],
            'traffic_counters' => $status['counters'],
            'database_latency_ms' => $status['database_latency_ms'],
            'host_metrics' => $status['host'],
            'job_status' => $jobs,
            'incidents' => $incidents,
            'notes' => 'No user-identifying events, raw logs, passkeys, backup paths or credentials are included.',
        ], 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
            'Content-Disposition' => 'attachment; filename="nextgn-sysop-diagnostics.json"',
        ]);
    }
}
