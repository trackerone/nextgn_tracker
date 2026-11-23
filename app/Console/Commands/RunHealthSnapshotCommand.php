<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\RuntimeJobGate;
use Illuminate\Console\Command;

class RunHealthSnapshotCommand extends Command
{
    protected $signature = 'runtime:health-snapshot';

    protected $description = 'Run health snapshot runtime job.';

    public function handle(RuntimeJobGate $runtimeJobGate): int
    {
        if (! $runtimeJobGate->canRun('health.snapshot')) {
            $this->info('Health snapshot skipped: runtime toggle is disabled.');

            return self::SUCCESS;
        }

        $this->error('This health-snapshot placeholder does not capture data. Use the scheduled ops:monitor command.');

        return self::FAILURE;
    }
}
