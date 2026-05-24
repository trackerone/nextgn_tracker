<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

it('allows each sysop controllable runtime job to run when enabled', function (string $jobKey, string $command): void {
    config()->set('runtime_jobs', [[
        'key' => $jobKey,
        'critical' => false,
        'sysop_controllable' => true,
    ]]);

    app(\App\Services\Settings\SiteSettingsRepository::class)
        ->set(sprintf('runtime.jobs.%s.enabled', $jobKey), true, 'bool');

    $exitCode = Artisan::call($command);

    expect($exitCode)->toBe(0);
})->with([
    ['metadata.refresh', 'runtime:metadata-refresh'],
    ['cache.warm', 'runtime:cache-warm'],
    ['health.snapshot', 'runtime:health-snapshot'],
    ['notification.digest', 'pm:digest daily'],
]);

it('skips each sysop controllable runtime job when disabled and logs skip event', function (string $jobKey, string $command): void {
    Log::spy();

    config()->set('runtime_jobs', [[
        'key' => $jobKey,
        'critical' => false,
        'sysop_controllable' => true,
    ]]);

    app(\App\Services\Settings\SiteSettingsRepository::class)
        ->set(sprintf('runtime.jobs.%s.enabled', $jobKey), false, 'bool');

    $exitCode = Artisan::call($command);

    expect($exitCode)->toBe(0);

    Log::shouldHaveReceived('info')->withArgs(static function (string $message, array $context) use ($jobKey): bool {
        return $message === 'Runtime job skipped because it is disabled by sysop toggle.'
            && ($context['event'] ?? null) === 'runtime.job.skipped'
            && ($context['job_key'] ?? null) === $jobKey;
    })->once();
})->with([
    ['metadata.refresh', 'runtime:metadata-refresh'],
    ['cache.warm', 'runtime:cache-warm'],
    ['health.snapshot', 'runtime:health-snapshot'],
    ['notification.digest', 'pm:digest daily'],
]);
