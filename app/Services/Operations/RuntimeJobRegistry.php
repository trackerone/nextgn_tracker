<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Support\Facades\Artisan;
use Throwable;

final class RuntimeJobRegistry
{
    public function __construct(
        private readonly SiteSettingsRepository $settings,
        private readonly OperationsTelemetry $telemetry,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $jobStates = $this->telemetry->jobs();
        $scheduleListing = $this->declaredScheduleListing();

        return array_map(function (array $job) use ($jobStates, $scheduleListing): array {
            $key = (string) $job['key'];
            $implementation = (string) ($job['implementation'] ?? 'manual');
            $command = (string) ($job['command'] ?? '');
            $isScheduled = $implementation === 'scheduled';
            $scheduleRegistered = $isScheduled && $scheduleListing !== null
                ? $command !== '' && str_contains($scheduleListing, $command)
                : null;
            $state = $command !== '' ? ($jobStates[$command] ?? null) : null;

            return [
                ...$job,
                'enabled' => $this->isEnabled($key),
                'setting_key' => $this->settingKey($key),
                'implementation' => $implementation,
                'can_toggle' => $this->canToggle($job),
                'schedule_registered' => $scheduleRegistered,
                'last_success_at' => $state['last_success_at'] ?? null,
                'last_failed_at' => $state['last_failed_at'] ?? null,
                'execution_state' => $state['state'] ?? null,
            ];
        }, config('runtime_jobs', []));
    }

    public function isEnabled(string $jobKey): bool
    {
        $job = $this->find($jobKey);

        if ($job === null) {
            return false;
        }

        // Core tracker functionality is not disabled by an old or manually
        // inserted setting. Its runtime and permissions stay server-owned.
        if ($job['critical'] ?? false) {
            return true;
        }

        return $this->settings->getBool($this->settingKey($jobKey));
    }

    public function update(string $jobKey, bool $enabled): bool
    {
        $job = $this->find($jobKey);

        if ($job === null || ! $this->canToggle($job)) {
            return false;
        }

        $this->settings->set($this->settingKey($jobKey), $enabled, 'bool');

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $jobKey): ?array
    {
        foreach (config('runtime_jobs', []) as $job) {
            if (($job['key'] ?? null) === $jobKey) {
                return $job;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function canToggle(array $job): bool
    {
        return ! ($job['critical'] ?? false)
            && ($job['sysop_controllable'] ?? false)
            && ($job['implementation'] ?? 'manual') !== 'placeholder';
    }

    /**
     * The console bootstrap attaches the app's withSchedule definitions;
     * directly resolving Schedule in a web request can return an incomplete
     * event list. Laravel's schedule:list invokes that bootstrap and only
     * reads definitions. It never executes a scheduled task.
     */
    private function declaredScheduleListing(): ?string
    {
        try {
            $exitCode = Artisan::call('schedule:list', ['--no-ansi' => true]);
            $listing = trim(Artisan::output());

            return $exitCode === 0 && $listing !== '' ? $listing : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function settingKey(string $jobKey): string
    {
        return sprintf('runtime.jobs.%s.enabled', $jobKey);
    }
}
