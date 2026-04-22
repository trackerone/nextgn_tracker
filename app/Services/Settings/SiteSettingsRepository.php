<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\SiteSetting;

final class SiteSettingsRepository
{
    /** @var array<string, array{value: string, type: string}> */
    private const FALLBACKS = [
        'tracker.ratio.enforcement_enabled' => ['value' => 'true', 'type' => 'bool'],
        'tracker.ratio.minimum_download_ratio' => ['value' => '0.5', 'type' => 'float'],
        'tracker.ratio.freeleech_bypass_enabled' => ['value' => 'true', 'type' => 'bool'],
        'tracker.ratio.no_history_grace_enabled' => ['value' => 'true', 'type' => 'bool'],
    ];

    public function getBool(string $key): bool
    {
        return filter_var($this->value($key), FILTER_VALIDATE_BOOLEAN);
    }

    public function getFloat(string $key): float
    {
        return (float) $this->value($key);
    }

    public function getInt(string $key): int
    {
        return (int) $this->value($key);
    }

    public function getString(string $key): string
    {
        return $this->value($key);
    }

    public function set(string $key, mixed $value, string $type): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->normalizeValue($value, $type),
                'type' => $type,
            ],
        );
    }

    private function value(string $key): string
    {
        $setting = SiteSetting::query()->where('key', $key)->first();

        if ($setting instanceof SiteSetting) {
            return (string) $setting->value;
        }

        return self::FALLBACKS[$key]['value'] ?? '';
    }

    private function normalizeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => $value ? 'true' : 'false',
            'int' => (string) (int) $value,
            'float' => (string) (float) $value,
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }
}
