<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Support\Operations\SensitiveLogRedactor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bounded operational counters and persisted heartbeats.
 * No peer IP, passkey, user ID, auth header or request URL is recorded.
 */
final class OperationsTelemetry
{
    private const COUNTERS = ['announce_ok', 'announce_rejected', 'scrape_ok', 'scrape_rejected', 'http_ok', 'http_5xx'];

    public function __construct(private readonly SensitiveLogRedactor $redactor) {}

    public function count(string $counter): void
    {
        if (! in_array($counter, self::COUNTERS, true)) {
            return;
        }

        try {
            $cache = Cache::store();
            $key = 'nextgn:ops:minute:'.now()->format('YmdHi').':'.$counter;
            $cache->add($key, 0, 86400);
            $cache->increment($key);
        } catch (Throwable) {
            // Telemetry must never break the announce, scrape or HTTP hot path.
        }
    }

    /**
     * @return array<string, int|null>
     */
    public function counters(int $minutes = 15): array
    {
        $minutes = max(1, min(60, $minutes));
        $result = array_fill_keys(self::COUNTERS, 0);
        $seen = ['announce' => false, 'scrape' => false, 'http' => false];

        try {
            $cache = Cache::store();

            for ($i = 0; $i < $minutes; $i++) {
                $bucket = now()->subMinutes($i)->format('YmdHi');
                foreach (self::COUNTERS as $counter) {
                    $value = $cache->get('nextgn:ops:minute:'.$bucket.':'.$counter);
                    if ($value !== null) {
                        $seen[explode('_', $counter, 2)[0]] = true;
                        $result[$counter] += max(0, (int) $value);
                    }
                }
            }
        } catch (Throwable) {
            $seen = ['announce' => false, 'scrape' => false, 'http' => false];
        }

        foreach (self::COUNTERS as $counter) {
            if ($seen[explode('_', $counter, 2)[0]] === false) {
                $result[$counter] = null;
            }
        }

        return $result;
    }

    public function job(string $jobKey, string $state, ?int $durationMs = null, ?string $error = null): void
    {
        if (! in_array($state, ['started', 'succeeded', 'failed'], true)
            || preg_match('/\A[a-z0-9._:-]{1,100}\z/', $jobKey) !== 1) {
            return;
        }

        $when = now();
        $values = [
            'state' => $state,
            'updated_at' => $when,
        ];

        if ($state === 'started') {
            $values['last_started_at'] = $when;
        } elseif ($state === 'succeeded') {
            $values['last_success_at'] = $when;
            $values['last_duration_ms'] = $durationMs === null ? null : min(3600000, max(0, $durationMs));
            $values['last_error'] = null;
        } else {
            $values['last_failed_at'] = $when;
            $values['last_error'] = mb_substr($this->redactor->text($error ?? 'Task failed'), 0, 500);
        }

        try {
            DB::table('ops_job_status')->updateOrInsert(
                ['job_key' => $jobKey],
                ['created_at' => $when, ...$values]
            );
        } catch (Throwable) {
            // Failed telemetry storage must not cause a scheduled job to fail.
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function jobs(): array
    {
        try {
            return DB::table('ops_job_status')
                ->get(['job_key', 'state', 'last_started_at', 'last_success_at', 'last_failed_at', 'last_duration_ms', 'last_error'])
                ->mapWithKeys(static fn (object $row): array => [(string) $row->job_key => (array) $row])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function lastSuccess(string $jobKey): ?Carbon
    {
        try {
            $time = DB::table('ops_job_status')->where('job_key', $jobKey)->value('last_success_at');

            return $time ? Carbon::parse((string) $time) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
