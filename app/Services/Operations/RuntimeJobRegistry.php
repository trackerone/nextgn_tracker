<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Console\Scheduling\Schedule;
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
        $scheduleCommands = $this->declaredScheduleCommands();

        return array_map(function (array $job) use ($jobStates, $scheduleCommands): array {
            $key = (string) $job['key'];
            $implementation = (string) ($job['implementation'] ?? 'manual');
            $command = (string) ($job['command'] ?? '');
            $isScheduled = $implementation === 'scheduled';
            $scheduleRegistered = $isScheduled && $scheduleCommands !== null
                ? $this->scheduleContains($scheduleCommands, $command)
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
     * Reading the scheduler event registry does not run a task and does not
     * demonstrate that cron/systemd has executed schedule:run.
     *
     * @return list<string>|null
     */
    private function declaredScheduleCommands(): ?array
    {
        try {
            $commands = [];
            foreach (app(Schedule::class)->events() as $event) {
                if (is_string($event->command ?? null)) {
                    $commands[] = $event->command;
                }
            }

            return $commands;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $scheduleCommands
     */
    private function scheduleContains(array $scheduleCommands, string $command): bool
    {
        if ($command === '') {
            return false;
        }

        foreach ($scheduleCommands as $scheduled) {
            if (str_contains($scheduled, $command)) {
                return true;
            }
        }

        return false;
    }

    private function settingKey(string $jobKey): string
    {
        return sprintf('runtime.jobs.%s.enabled', $jobKey);
    }
}
