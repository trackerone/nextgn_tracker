<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * Member-class promotion thresholds only. Download eligibility is governed
 * separately by tracker.ratio.* and must never be derived from these keys.
 */
final class RatioSettings
{
    private const KEYS = [
        'elite_min_ratio' => 'ratio.elite_min_ratio',
        'power_user_min_ratio' => 'ratio.power_user_min_ratio',
        'power_user_min_downloaded' => 'ratio.power_user_min_downloaded',
        'user_min_ratio' => 'ratio.user_min_ratio',
        'tenure_enabled' => 'ratio.tenure_enabled',
        'user_min_months' => 'ratio.user_min_months',
        'power_user_min_months' => 'ratio.power_user_min_months',
        'elite_min_months' => 'ratio.elite_min_months',
    ];

    /** @var array<string, bool|int|float>|null */
    private ?array $resolved = null;

    public function __construct(private readonly SiteSettingsRepository $repository) {}

    /**
     * One batched read with request-instance caching; no long-lived cache to
     * invalidate across workers after a Sysop/Admin change.
     *
     * @return array<string, bool|int|float>
     */
    private function settings(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $overrides = $this->repository->getMany(array_values(self::KEYS));
        $values = [];

        foreach (self::KEYS as $name => $key) {
            $definition = SettingsRegistry::definition($key);
            if ($definition === null) {
                throw new \LogicException('Missing class threshold definition: '.$key);
            }

            $values[$name] = $this->parse($overrides[$key] ?? null, $definition);
        }

        return $this->resolved = $values;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function parse(?string $raw, array $definition): bool|int|float
    {
        $default = $definition['default'];

        if ($raw === null) {
            return $default;
        }

        if ($definition['type'] === 'bool') {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        if ($definition['type'] === 'int') {
            $value = filter_var($raw, FILTER_VALIDATE_INT);

            return $value !== false && $this->withinBounds($value, $definition) ? $value : $default;
        }

        if (! is_numeric($raw)) {
            return $default;
        }

        $value = (float) $raw;

        return is_finite($value) && $this->withinBounds($value, $definition) ? $value : $default;
    }

    /** @param array<string, mixed> $definition */
    private function withinBounds(float|int $value, array $definition): bool
    {
        return (! isset($definition['min']) || $value >= $definition['min'])
            && (! isset($definition['max']) || $value <= $definition['max']);
    }

    public function eliteMinRatio(): float
    {
        return $this->settings()['elite_min_ratio'];
    }

    public function powerUserMinRatio(): float
    {
        return $this->settings()['power_user_min_ratio'];
    }

    public function powerUserMinDownloaded(): int
    {
        return $this->settings()['power_user_min_downloaded'];
    }

    public function userMinRatio(): float
    {
        return $this->settings()['user_min_ratio'];
    }

    public function tenureEnabled(): bool
    {
        return $this->settings()['tenure_enabled'];
    }

    public function userMinMonths(): int
    {
        return $this->settings()['user_min_months'];
    }

    public function powerUserMinMonths(): int
    {
        return $this->settings()['power_user_min_months'];
    }

    public function eliteMinMonths(): int
    {
        return $this->settings()['elite_min_months'];
    }

    /** Refresh the same service instance after an update within this request. */
    public function flush(): void
    {
        $this->resolved = null;
    }
}
