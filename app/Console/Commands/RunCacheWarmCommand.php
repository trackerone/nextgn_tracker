<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\RuntimeJobGate;
use Illuminate\Console\Command;

class RunCacheWarmCommand extends Command
{
    protected $signature = 'runtime:cache-warm';

    protected $description = 'Run cache warm runtime job.';

    public function handle(RuntimeJobGate $runtimeJobGate): int
    {
        if (! $runtimeJobGate->canRun('cache.warm')) {
            $this->info('Cache warm skipped: runtime toggle is disabled.');

            return self::SUCCESS;
        }

        $this->error('Cache warmup is not implemented. No caches were warmed.');

        return self::FAILURE;
    }
}
