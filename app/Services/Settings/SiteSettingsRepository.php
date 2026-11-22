<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\SiteSetting;

final class SiteSettingsRepository
{
    /**
     * Raw persisted overrides only; callers such as HnR and upload validation
     * retain their existing batch-read and fallback behavior.
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function getMany(array $keys): array
    {
        return SiteSetting::query()->whereIn('key', $keys)->pluck('value', 'key')->all();
    }

    public function getBool(string $key): bool
    {
        $default = (bool) (SettingsRegistry::definition($key)['default'] ?? false);
        $raw = $this->storedValue($key);
        if ($raw === null) {
            return $default;
        }

        $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    public function getFloat(string $key): float
    {
        $definition = SettingsRegistry::definition($key);
        $default = (float) ($definition['default'] ?? 0.0);
        $raw = $this->storedValue($key);

        if ($raw === null || ! is_numeric($raw)) {
            return $default;
        }

        $value = (float) $raw;
        if (! is_finite($value) || ! $this->withinBounds($value, $definition)) {
            return $default;
        }

        return $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $definition = SettingsRegistry::definition($key);
        $fallback = (int) ($definition['default'] ?? $default);
        $raw = $this->storedValue($key);
        if ($raw === null) {
            return $fallback;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || ! $this->withinBounds($value, $definition)) {
            return $fallback;
        }

        return $value;
    }

    public function getString(string $key): string
    {
        $definition = SettingsRegistry::definition($key);
        $fallback = (string) ($definition['default'] ?? '');
        $raw = $this->storedValue($key);
        if ($raw === null) {
            return $fallback;
        }

        if (isset($definition['allowed']) && ! in_array($raw, $definition['allowed'], true)) {
            return $fallback;
        }

        return $raw;
    }

    /**
     * @return array<mixed>
     */
    public function getJson(string $key): array
    {
        $definition = SettingsRegistry::definition($key);
        $default = $definition['default'] ?? [];
        $raw = $this->storedValue($key);
        if ($raw === null) {
            return is_array($default) ? $default : [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : (is_array($default) ? $default : []);
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

    private function storedValue(string $key): ?string
    {
        $value = SiteSetting::query()->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>|null  $definition
     */
    private function withinBounds(float|int $value, ?array $definition): bool
    {
        return ($definition === null || ! isset($definition['min']) || $value >= $definition['min'])
            && ($definition === null || ! isset($definition['max']) || $value <= $definition['max']);
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
