<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Operations\OperationsTelemetry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deliberately tiny job: a successful execution proves an async worker consumed
 * work. An empty failed_jobs table is not proof of a running worker.
 */
final class OperationsQueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(OperationsTelemetry $telemetry): void
    {
        $telemetry->job('queue.worker', 'succeeded');
    }
}
