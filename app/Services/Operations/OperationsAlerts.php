<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Throwable;

final class OperationsAlerts
{
    public function __construct(private readonly OperationsNotifier $notifier) {}

    /**
     * Dedupe incidents by stable component keys; resolve automatically on recovery.
     * Telemetry and alerts never execute remediation commands.
     *
     * @param  array<int, array{id:string,label:string,status:string,details:string}>  $checks
     */
    public function reconcile(array $checks): void
    {
        foreach ($checks as $check) {
            $fingerprint = 'health:'.$check['id'];
            $severity = $check['status'];

            if ($severity === 'healthy') {
                $this->resolve($fingerprint);
                continue;
            }

            if (in_array($severity, ['degraded', 'critical'], true) === false) {
                // Missing telemetry is not evidence that an incident recovered.
                continue;
            }

            try {
                $row = DB::table('ops_alerts')->where('fingerprint', $fingerprint)->first();
                $now = now();

                if ($row === null) {
                    DB::table('ops_alerts')->insert([
                        'fingerprint' => $fingerprint,
                        'component' => $check['id'],
                        'title' => $check['label'].' needs attention',
                        'severity' => $severity,
                        'status' => 'active',
                        'occurrences' => 1,
                        'first_seen_at' => $now,
                        'last_seen_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif ($row->status === 'resolved') {
                    DB::table('ops_alerts')->where('id', $row->id)->update([
                        'severity' => $severity,
                        'status' => 'active',
                        'occurrences' => 1,
                        'first_seen_at' => $now,
                        'last_seen_at' => $now,
                        'acknowledged_at' => null,
                        'resolved_at' => null,
                        'notified_at' => null,
                        'updated_at' => $now,
                    ]);
                } elseif ($row->last_seen_at === null || \Illuminate\Support\Carbon::parse((string) $row->last_seen_at)->diffInMinutes(now()) >= 5) {
                    DB::table('ops_alerts')->where('id', $row->id)->update([
                        'severity' => $severity,
                        'occurrences' => min(2147483647, (int) $row->occurrences + 1),
                        'last_seen_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } catch (Throwable) {
                // A DB failure may itself be the incident; do not crash the monitor.
            }

            if ($severity === 'critical') {
                $this->notifier->sendCritical($fingerprint);
            }
        }
    }

    private function resolve(string $fingerprint): void
    {
        try {
            DB::table('ops_alerts')
                ->where('fingerprint', $fingerprint)
                ->where('status', '<>', 'resolved')
                ->update(['status' => 'resolved', 'resolved_at' => now(), 'updated_at' => now()]);
        } catch (Throwable) {
        }
    }

    /**
     * @return array<int, object>
     */
    public function recent(int $limit = 20): array
    {
        try {
            return DB::table('ops_alerts')
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'acknowledged' THEN 1 ELSE 2 END")
                ->orderByDesc('last_seen_at')
                ->limit(max(1, min(100, $limit)))
                ->get()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function updateStatus(int $id, string $action): bool
    {
        if (in_array($action, ['acknowledge', 'resolve'], true) === false) {
            return false;
        }

        $values = $action === 'acknowledge'
            ? ['status' => 'acknowledged', 'acknowledged_at' => now()]
            : ['status' => 'resolved', 'resolved_at' => now()];

        return DB::table('ops_alerts')
            ->where('id', $id)
            ->whereIn('status', $action === 'acknowledge' ? ['active'] : ['active', 'acknowledged'])
            ->update([...$values, 'updated_at' => now()]) > 0;
    }
}
