<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Services\Hnr\HnrSettings;

/**
 * Describes owner-configurable settings without moving existing values.
 *
 * Precedence: persisted site_settings override > declared default > legacy
 * caller fallback for unregistered keys. No in-process cache: every lookup
 * observes the latest committed Sysop change.
 */
final class SettingsRegistry
{
    /**
     * Defaults and metadata for existing site_settings keys. The registry is
     * not a second store and must never materialize defaults in the database.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SITE = [
        'registration.mode' => [
            'type' => 'string', 'default' => 'invite', 'owner' => 'sysop',
            'allowed' => ['closed', 'invite', 'open'],
        ],
        'tracker.ratio.enforcement_enabled' => [
            'type' => 'bool', 'default' => true, 'owner' => 'sysop',
        ],
        'tracker.ratio.minimum_download_ratio' => [
            'type' => 'float', 'default' => 0.5, 'owner' => 'sysop', 'min' => 0, 'max' => 10,
        ],
        'tracker.ratio.freeleech_bypass_enabled' => [
            'type' => 'bool', 'default' => true, 'owner' => 'sysop',
        ],
        'tracker.ratio.no_history_grace_enabled' => [
            'type' => 'bool', 'default' => true, 'owner' => 'sysop',
        ],
        'metadata.providers.tmdb.enabled' => [
            'type' => 'bool', 'default' => true, 'owner' => 'sysop',
        ],
        'metadata.providers.trakt.enabled' => [
            'type' => 'bool', 'default' => true, 'owner' => 'sysop',
        ],
        'metadata.providers.imdb.enabled' => [
            'type' => 'bool', 'default' => false, 'owner' => 'sysop',
        ],
        'metadata.providers.priority' => [
            'type' => 'json', 'default' => ['tmdb', 'trakt', 'imdb'], 'owner' => 'sysop',
        ],
        'metadata.enrichment.enabled' => [
            'type' => 'bool', 'default' => true, 'owner' => 'sysop',
        ],
        'metadata.enrichment.auto_on_publish' => [
            'type' => 'bool', 'default' => true, 'owner' => 'sysop',
        ],
        'metadata.enrichment.refresh_after_days' => [
            'type' => 'int', 'default' => 30, 'owner' => 'sysop', 'min' => 1,
        ],
    ];

    /** @var array<string, array<string, mixed>> */
    private const CLASS_THRESHOLDS = [
        'ratio.elite_min_ratio' => ['type' => 'float', 'min' => 0, 'max' => 10, 'owner' => 'admin'],
        'ratio.power_user_min_ratio' => ['type' => 'float', 'min' => 0, 'max' => 10, 'owner' => 'admin'],
        'ratio.power_user_min_downloaded' => ['type' => 'int', 'min' => 0, 'max' => 10000000000, 'owner' => 'admin'],
        'ratio.user_min_ratio' => ['type' => 'float', 'min' => 0, 'max' => 10, 'owner' => 'admin'],
        'ratio.tenure_enabled' => ['type' => 'bool', 'owner' => 'sysop'],
        'ratio.user_min_months' => ['type' => 'int', 'min' => 0, 'max' => 1200, 'owner' => 'sysop'],
        'ratio.power_user_min_months' => ['type' => 'int', 'min' => 0, 'max' => 1200, 'owner' => 'sysop'],
        'ratio.elite_min_months' => ['type' => 'int', 'min' => 0, 'max' => 1200, 'owner' => 'sysop'],
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function definition(string $key): ?array
    {
        if (isset(self::SITE[$key])) {
            return self::siteDefinition($key, self::SITE[$key]);
        }

        if (isset(self::CLASS_THRESHOLDS[$key])) {
            return self::siteDefinition($key, [
                ...self::CLASS_THRESHOLDS[$key],
                'default' => config('ratio.'.substr($key, 6)),
                'default_source' => 'config/ratio.php',
                'cache' => 'request-local',
            ]);
        }

        $tracker = [
            TrackerRuntimeSettings::ANNOUNCE_INTERVAL => ['tracker.announce_interval_seconds', 1800, 60, 7200],
            TrackerRuntimeSettings::GHOST_TIMEOUT => ['tracker.ghost_peer_timeout_minutes', 45, 2, 1440],
            TrackerRuntimeSettings::SCRAPE_LIMIT => ['tracker.scrape_max_info_hashes', 50, 1, 200],
        ];
        if (isset($tracker[$key])) {
            [$config, $fallback, $min, $max] = $tracker[$key];

            return self::siteDefinition($key, [
                'type' => 'int',
                'default' => (int) config($config, $fallback),
                'owner' => 'sysop',
                'min' => $min,
                'max' => $max,
                'default_source' => $config,
            ]);
        }

        foreach (UploadLimits::DEFINITIONS as $upload) {
            if ($upload['key'] === $key) {
                return self::siteDefinition($key, [
                    'type' => 'int',
                    'default' => (int) config($upload['config'], $upload['fallback']),
                    'owner' => 'sysop',
                    'min' => $upload['min'],
                    'max' => $upload['max'],
                    'default_source' => $upload['config'],
                ]);
            }
        }

        if (str_starts_with($key, 'hnr.')) {
            $name = substr($key, 4);
            if (array_key_exists($name, HnrSettings::DEFAULTS)) {
                $default = HnrSettings::DEFAULTS[$name];

                return self::siteDefinition($key, [
                    'type' => is_bool($default) ? 'bool' : (is_float($default) ? 'float' : 'int'),
                    'default' => $default,
                    'owner' => 'sysop',
                ]);
            }
        }

        if (str_starts_with($key, 'runtime.jobs.')) {
            foreach (config('runtime_jobs', []) as $job) {
                if (! is_array($job) || ! is_string($job['key'] ?? null)) {
                    continue;
                }
                if ('runtime.jobs.'.$job['key'].'.enabled' === $key) {
                    return self::siteDefinition($key, [
                        'type' => 'bool',
                        'default' => (bool) ($job['default_enabled'] ?? false),
                        'owner' => ($job['sysop_controllable'] ?? false) && ! ($job['critical'] ?? false) ? 'sysop' : 'server',
                        'default_source' => 'runtime_jobs',
                    ]);
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function classThresholds(): array
    {
        $definitions = [];
        foreach (array_keys(self::CLASS_THRESHOLDS) as $key) {
            $definition = self::definition($key);
            if ($definition !== null) {
                $definitions[$key] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private static function siteDefinition(string $key, array $definition): array
    {
        return [
            'key' => $key,
            'store' => 'site_settings',
            'cache' => 'none',
            'precedence' => 'site_settings > registered default',
            'default_source' => 'registry',
            ...$definition,
        ];
    }
}
