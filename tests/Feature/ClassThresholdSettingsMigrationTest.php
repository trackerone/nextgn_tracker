<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Settings\RatioSettings;
use App\Services\Tracker\RatioRulesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClassThresholdSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_legacy_overrides_are_backfilled_without_erasing_old_rows(): void
    {
        Setting::setValue('ratio.elite_min_ratio', '2.5');
        Setting::setValue('ratio.tenure_enabled', '1');
        Setting::setValue('ratio.elite_min_months', '48');

        $migration = require database_path('migrations/2026_11_22_000500_backfill_class_thresholds_into_site_settings.php');
        $migration->up();

        $this->assertDatabaseHas('site_settings', ['key' => 'ratio.elite_min_ratio', 'value' => '2.5', 'type' => 'float']);
        $this->assertDatabaseHas('site_settings', ['key' => 'ratio.tenure_enabled', 'value' => 'true', 'type' => 'bool']);
        $this->assertDatabaseHas('site_settings', ['key' => 'ratio.elite_min_months', 'value' => '48', 'type' => 'int']);
        $this->assertDatabaseHas('settings', ['key' => 'ratio.elite_min_ratio', 'value' => '2.5']);

        $settings = app(RatioSettings::class);
        $this->assertSame(2.5, $settings->eliteMinRatio());
        $this->assertTrue($settings->tenureEnabled());
        $this->assertSame(48, $settings->eliteMinMonths());

        // Migration may safely be retried without changing owner-defined values.
        $migration->up();
        $this->assertSame(1, SiteSetting::query()->where('key', 'ratio.elite_min_ratio')->count());
    }

    public function test_canonical_override_wins_and_invalid_legacy_values_are_not_imported(): void
    {
        SiteSetting::query()->create(['key' => 'ratio.elite_min_ratio', 'value' => '1.9', 'type' => 'float']);
        Setting::setValue('ratio.elite_min_ratio', '2.8');
        Setting::setValue('ratio.power_user_min_ratio', 'not-a-ratio');
        Setting::setValue('ratio.user_min_months', '-100');

        $migration = require database_path('migrations/2026_11_22_000500_backfill_class_thresholds_into_site_settings.php');
        $migration->up();

        $this->assertSame(1.9, app(RatioSettings::class)->eliteMinRatio());
        $this->assertDatabaseMissing('site_settings', ['key' => 'ratio.power_user_min_ratio']);
        $this->assertDatabaseMissing('site_settings', ['key' => 'ratio.user_min_months']);
        $this->assertSame(0.8, app(RatioSettings::class)->powerUserMinRatio());
        $this->assertSame(3, app(RatioSettings::class)->userMinMonths());
    }

    public function test_threshold_edits_write_one_canonical_store_without_changing_download_eligibility(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->actingAs($sysop)->patch(route('admin.settings.ratio.update'), [
            'elite_min_ratio' => 2.0,
            'power_user_min_ratio' => 1.0,
            'power_user_min_downloaded' => 1500,
            'user_min_ratio' => 0.6,
            'tenure_enabled' => '1',
            'user_min_months' => 4,
            'power_user_min_months' => 15,
            'elite_min_months' => 40,
        ])->assertRedirect(route('sysop.permissions.index').'#class-thresholds');

        $this->assertDatabaseHas('site_settings', ['key' => 'ratio.user_min_ratio', 'value' => '0.6']);
        $this->assertDatabaseHas('site_settings', ['key' => 'ratio.tenure_enabled', 'value' => 'true']);
        $this->assertDatabaseHas('site_settings', ['key' => 'ratio.elite_min_months', 'value' => '40']);
        $this->assertDatabaseMissing('settings', ['key' => 'ratio.user_min_ratio']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.ratio.updated']);

        $this->assertSame(2.0, app(RatioSettings::class)->eliteMinRatio());
        $this->assertSame(40, app(RatioSettings::class)->eliteMinMonths());
        $this->assertSame(0.5, app(RatioRulesConfig::class)->minimumDownloadRatio());
    }

    public function test_invalid_canonical_override_falls_back_without_disabling_promotion_policy(): void
    {
        SiteSetting::query()->create(['key' => 'ratio.elite_min_ratio', 'value' => 'INF', 'type' => 'float']);
        SiteSetting::query()->create(['key' => 'ratio.tenure_enabled', 'value' => 'invalid', 'type' => 'bool']);
        SiteSetting::query()->create(['key' => 'ratio.power_user_min_downloaded', 'value' => '-3', 'type' => 'int']);

        $settings = app(RatioSettings::class);
        $this->assertSame(1.5, $settings->eliteMinRatio());
        $this->assertFalse($settings->tenureEnabled());
        $this->assertSame(1000, $settings->powerUserMinDownloaded());
    }

    public function test_rollback_copies_latest_class_thresholds_to_legacy_store_without_deleting_canonical_values(): void
    {
        Setting::setValue('ratio.user_min_ratio', '0.3');
        $migration = require database_path('migrations/2026_11_22_000500_backfill_class_thresholds_into_site_settings.php');
        $migration->up();

        SiteSetting::query()->where('key', 'ratio.user_min_ratio')->update(['value' => '0.7']);
        $migration->down();

        $this->assertSame('0.7', Setting::valueForKey('ratio.user_min_ratio'));
        $this->assertDatabaseHas('site_settings', ['key' => 'ratio.user_min_ratio', 'value' => '0.7']);
    }
}
