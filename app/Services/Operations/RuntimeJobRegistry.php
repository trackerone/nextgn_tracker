<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Services\Settings\SiteSettingsRepository;

final class RuntimeJobRegistry
{
    public function __construct(private readonly SiteSettingsRepository $settings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(function (array $job): array {
            $key = (string) $job['key'];

            return [
                ...$job,
                'enabled' => $this->isEnabled($key),
                'setting_key' => $this->settingKey($key),
            ];
        }, config('runtime_jobs', []));
    }

    public function isEnabled(string $jobKey): bool
    {
        $job = $this->find($jobKey);

        if ($job === null) {
            return false;
        }

        return $this->settings->getBool($this->settingKey($jobKey));
    }

    public function update(string $jobKey, bool $enabled): bool
    {
        $job = $this->find($jobKey);

        if ($job === null || ($job['critical'] ?? false) || ! ($job['sysop_controllable'] ?? false)) {
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

    private function settingKey(string $jobKey): string
    {
        return sprintf('runtime.jobs.%s.enabled', $jobKey);
    }
}
