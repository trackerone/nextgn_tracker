<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\Settings\RegistrationPolicy;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SiteSettingsRepository;
use App\Services\Settings\TrackerRuntimeSettings;
use App\Services\Tracker\RatioRulesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SettingsRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_describes_existing_settings_without_creating_or_moving_rows(): void
    {
        $runtime = SettingsRegistry::definition(TrackerRuntimeSettings::ANNOUNCE_INTERVAL);
        $this->assertSame('site_settings', $runtime['store']);
        $this->assertSame('int', $runtime['type']);
        $this->assertSame('sysop', $runtime['owner']);
        $this->assertSame('none', $runtime['cache']);
        $this->assertSame('tracker.announce_interval_seconds', $runtime['default_source']);
        $this->assertSame(60, $runtime['min']);
        $this->assertSame(7200, $runtime['max']);

        $this->assertSame(
            ['closed', 'invite', 'open'],
            SettingsRegistry::definition(RegistrationPolicy::SETTING_KEY)['allowed']
        );

        $this->assertSame(
            'site_settings',
            SettingsRegistry::definition('hnr.seed_hours')['store']
        );
        $this->assertSame(
            72,
            SettingsRegistry::definition('hnr.seed_hours')['default']
        );
        $this->assertSame(
            'server',
            SettingsRegistry::definition('runtime.jobs.tracker.ratio.accounting.enabled')['owner']
        );
        $this->assertSame(
            'sysop',
            SettingsRegistry::definition('runtime.jobs.metadata.refresh.enabled')['owner']
        );
        $this->assertSame(
            'int',
            SettingsRegistry::definition('uploads.torrents.max_kilobytes')['type']
        );
        $this->assertNull(SettingsRegistry::definition('arbitrary.unregistered.key'));

        $legacy = SettingsRegistry::legacyClassThresholds();
        $this->assertSame('settings', $legacy['ratio.elite_min_ratio']['store']);
        $this->assertSame('settings.ratio', $legacy['ratio.elite_min_ratio']['cache']);
        $this->assertSame('settings > config/ratio.php', $legacy['ratio.elite_min_ratio']['precedence']);

        $this->assertDatabaseMissing('site_settings', ['key' => 'hnr.seed_hours']);
        $this->assertDatabaseMissing('site_settings', ['key' => 'uploads.torrents.max_kilobytes']);
        $this->assertDatabaseMissing('settings', ['key' => 'ratio.elite_min_ratio']);
    }

    public function test_persisted_ratio_override_wins_and_invalid_values_fall_back_safely(): void
    {
        $settings = app(SiteSettingsRepository::class);
        $ratio = app(RatioRulesConfig::class);

        SiteSetting::query()->where('key', RatioRulesConfig::ENFORCEMENT_ENABLED)->delete();
        $this->assertTrue($ratio->enforcementEnabled());

        $settings->set(RatioRulesConfig::ENFORCEMENT_ENABLED, false, 'bool');
        $this->assertFalse($ratio->enforcementEnabled());

        $settings->set(RatioRulesConfig::ENFORCEMENT_ENABLED, 'not-a-boolean', 'string');
        $this->assertTrue($ratio->enforcementEnabled());

        $settings->set(RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO, 'not-a-ratio', 'string');
        $this->assertSame(0.5, $ratio->minimumDownloadRatio());

        $settings->set(RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO, 999, 'float');
        $this->assertSame(0.5, $ratio->minimumDownloadRatio());

        $settings->set(RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO, 0.75, 'float');
        $this->assertSame(0.75, $ratio->minimumDownloadRatio());
        $this->assertDatabaseHas('site_settings', [
            'key' => RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO,
            'value' => '0.75',
        ]);
    }

    public function test_valid_overrides_take_effect_immediately_with_no_stale_cache(): void
    {
        $settings = app(SiteSettingsRepository::class);
        $ratio = app(RatioRulesConfig::class);

        $settings->set(RatioRulesConfig::FREELEECH_BYPASS_ENABLED, false, 'bool');
        $this->assertFalse($ratio->freeleechBypassEnabled());

        $settings->set(RatioRulesConfig::FREELEECH_BYPASS_ENABLED, true, 'bool');
        $this->assertTrue($ratio->freeleechBypassEnabled());

        // Fresh service instances and a second request observe the same value.
        $this->assertTrue(app(RatioRulesConfig::class)->freeleechBypassEnabled());
        $this->get(route('login'))->assertOk();
        $this->assertTrue($ratio->freeleechBypassEnabled());
        $this->assertDatabaseHas('site_settings', [
            'key' => RatioRulesConfig::FREELEECH_BYPASS_ENABLED,
            'value' => 'true',
        ]);
    }

    public function test_runtime_fallback_respects_deployment_config_without_a_duplicate_default(): void
    {
        $key = TrackerRuntimeSettings::ANNOUNCE_INTERVAL;
        SiteSetting::query()->where('key', $key)->delete();

        config()->set('tracker.announce_interval_seconds', 900);
        $runtime = app(TrackerRuntimeSettings::class);
        $this->assertSame(900, $runtime->announceInterval());

        app(SiteSettingsRepository::class)->set($key, 300, 'int');
        $this->assertSame(300, $runtime->announceInterval());

        app(SiteSettingsRepository::class)->set($key, 'broken', 'string');
        $this->assertSame(900, $runtime->announceInterval());

        app(SiteSettingsRepository::class)->set($key, 9000, 'int');
        $this->assertSame(900, $runtime->announceInterval());
    }

    public function test_registered_bool_string_json_and_integer_defaults_are_type_safe(): void
    {
        $settings = app(SiteSettingsRepository::class);

        $settings->set(RegistrationPolicy::SETTING_KEY, 'invalid-mode', 'string');
        $this->assertSame('invite', app(RegistrationPolicy::class)->mode());

        $settings->set('metadata.providers.priority', 'malformed-json', 'string');
        $this->assertSame(['tmdb', 'trakt', 'imdb'], $settings->getJson('metadata.providers.priority'));

        $settings->set('metadata.enrichment.refresh_after_days', 'NaN', 'string');
        $this->assertSame(30, $settings->getInt('metadata.enrichment.refresh_after_days'));

        $settings->set('metadata.enrichment.refresh_after_days', -100, 'int');
        $this->assertSame(30, $settings->getInt('metadata.enrichment.refresh_after_days'));

        $settings->set('metadata.providers.imdb.enabled', 'unknown', 'string');
        $this->assertFalse($settings->getBool('metadata.providers.imdb.enabled'));

        $settings->set('metadata.providers.trakt.enabled', 'unknown', 'string');
        $this->assertTrue($settings->getBool('metadata.providers.trakt.enabled'));
    }

    public function test_batch_read_contract_still_returns_only_persisted_raw_overrides(): void
    {
        $settings = app(SiteSettingsRepository::class);
        $this->assertSame([], $settings->getMany(['hnr.seed_hours', 'registration.mode']));

        $settings->set('hnr.seed_hours', 96, 'int');
        $this->assertSame(['hnr.seed_hours' => '96'], $settings->getMany([
            'hnr.seed_hours',
            'registration.mode',
        ]));

        $this->assertSame(96, $settings->getInt('hnr.seed_hours'));
        $this->assertDatabaseMissing('site_settings', ['key' => 'registration.mode']);
    }
}
