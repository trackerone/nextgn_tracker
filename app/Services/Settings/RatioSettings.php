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
                ])
                ->pluck('value', 'key')
                ->toArray();

            return [
                'elite_min_ratio' => (float) ($overrides['ratio.elite_min_ratio'] ?? $default['elite_min_ratio']),
                'power_user_min_ratio' => (float) ($overrides['ratio.power_user_min_ratio'] ?? $default['power_user_min_ratio']),
                'power_user_min_downloaded' => (int) ($overrides['ratio.power_user_min_downloaded'] ?? $default['power_user_min_downloaded']),
                'user_min_ratio' => (float) ($overrides['ratio.user_min_ratio'] ?? $default['user_min_ratio']),
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

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
