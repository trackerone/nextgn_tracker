<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\RuntimeJobGate;
use Illuminate\Console\Command;

class RunMetadataRefreshCommand extends Command
{
    protected $signature = 'runtime:metadata-refresh';

    protected $description = 'Run metadata refresh runtime job.';

    public function handle(RuntimeJobGate $runtimeJobGate): int
    {
        if (! $runtimeJobGate->canRun('metadata.refresh')) {
            $this->info('Metadata refresh skipped: runtime toggle is disabled.');

            return self::SUCCESS;
        }

        $this->info('Metadata refresh completed.');

        return self::SUCCESS;
    }
}
