<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Support\Facades\Log;

final class RuntimeJobGate
{
    public function __construct(private readonly RuntimeJobRegistry $registry) {}

    public function canRun(string $jobKey, array $context = []): bool
    {
        $job = $this->registry->find($jobKey);

        if ($job === null || ! ($job['sysop_controllable'] ?? false)) {
            return true;
        }

        if ($this->registry->isEnabled($jobKey)) {
            return true;
        }

        Log::info('Runtime job skipped because it is disabled by sysop toggle.', [
            'event' => 'runtime.job.skipped',
            'job_key' => $jobKey,
            ...$context,
        ]);

        return false;
    }
}
