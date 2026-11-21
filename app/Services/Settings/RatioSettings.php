<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class RatioSettings
{
    private const CACHE_KEY = 'settings.ratio';

    /**
     * @return array{
     *     elite_min_ratio: float,
     *     power_user_min_ratio: float,
     *     power_user_min_downloaded: int,
     *     user_min_ratio: float,
     *     tenure_enabled: bool,
     *     user_min_months: int,
     *     power_user_min_months: int,
     *     elite_min_months: int,
     * }
     */
    private function settings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $default = config('ratio');

            $overrides = Setting::query()
                ->whereIn('key', [
                    'ratio.elite_min_ratio',
                    'ratio.power_user_min_ratio',
                    'ratio.power_user_min_downloaded',
                    'ratio.user_min_ratio',
                    'ratio.tenure_enabled',
                    'ratio.user_min_months',
                    'ratio.power_user_min_months',
                    'ratio.elite_min_months',
                ])
                ->pluck('value', 'key')
                ->toArray();

            return [
                'elite_min_ratio' => (float) ($overrides['ratio.elite_min_ratio'] ?? $default['elite_min_ratio']),
                'power_user_min_ratio' => (float) ($overrides['ratio.power_user_min_ratio'] ?? $default['power_user_min_ratio']),
                'power_user_min_downloaded' => (int) ($overrides['ratio.power_user_min_downloaded'] ?? $default['power_user_min_downloaded']),
                'user_min_ratio' => (float) ($overrides['ratio.user_min_ratio'] ?? $default['user_min_ratio']),
                'tenure_enabled' => filter_var($overrides['ratio.tenure_enabled'] ?? $default['tenure_enabled'], FILTER_VALIDATE_BOOLEAN),
                'user_min_months' => (int) ($overrides['ratio.user_min_months'] ?? $default['user_min_months']),
                'power_user_min_months' => (int) ($overrides['ratio.power_user_min_months'] ?? $default['power_user_min_months']),
                'elite_min_months' => (int) ($overrides['ratio.elite_min_months'] ?? $default['elite_min_months']),
            ];
        });
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

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
