<?php

declare(strict_types=1);

namespace App\Services\Hnr;

use App\Models\User;
use App\Services\Settings\SiteSettingsRepository;

final class HnrSettings
{
    public const DEFAULTS = [
        'enabled' => false,
        'seed_hours' => 72,
        'ratio' => 1.0,
        'grace_hours' => 120,
        'torrent_threshold' => 40,
        'warning_interval_days' => 31,
        'warning_decay_days' => 62,
        'warnings_before_restriction' => 3,
        'exempt_staff' => true,
        'exempt_uploaders' => true,
    ];

    public function __construct(private readonly SiteSettingsRepository $settings) {}

    public function values(): array
    {
        $stored = $this->settings->getMany(array_map(static fn (string $key): string => 'hnr.'.$key, array_keys(self::DEFAULTS)));
        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $raw = $stored['hnr.'.$key] ?? '';
            $values[$key] = $raw === '' ? $default : match (true) {
                is_bool($default) => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                is_float($default) => (float) $raw,
                default => (int) $raw,
            };
        }

        return $values;
    }

    public function enabled(): bool
    {
        return (bool) $this->values()['enabled'];
    }

    public function exempt(User $user): bool
    {
        $values = $this->values();

        return ($values['exempt_staff'] && $user->isStaff())
            || ($values['exempt_uploaders'] && $user->resolveRoleIdentifier() === User::ROLE_UPLOADER);
    }
}
