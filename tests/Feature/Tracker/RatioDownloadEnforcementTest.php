<?php

declare(strict_types=1);

namespace Tests\Feature\Tracker;

use App\Models\SiteSetting;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserStat;
use App\Services\BencodeService;
use App\Services\Tracker\DownloadEligibilityPolicy;
use App\Services\Tracker\RatioRulesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RatioDownloadEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_enforcement_disabled_always_allows(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->setRatioSettings([
            'enforcement_enabled' => false,
            'minimum_download_ratio' => 10,
            'freeleech_bypass_enabled' => false,
            'no_history_grace_enabled' => false,
        ]);

        $result = app(DownloadEligibilityPolicy::class)->check($user, $torrent);

        $this->assertTrue($result['allowed']);
        $this->assertSame('enforcement_disabled', $result['reason']);
    }

    public function test_ratio_below_threshold_denies_and_above_allows(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        UserStat::query()->create([
            'user_id' => $user->id,
            'uploaded_bytes' => 100,
            'downloaded_bytes' => 1_000,
        ]);

        $this->setRatioSettings([
            'enforcement_enabled' => true,
            'minimum_download_ratio' => 0.5,
            'freeleech_bypass_enabled' => false,
            'no_history_grace_enabled' => false,
        ]);

        $denied = app(DownloadEligibilityPolicy::class)->check($user, $torrent);
        $this->assertFalse($denied['allowed']);
        $this->assertSame('ratio_too_low', $denied['reason']);

        UserStat::query()->where('user_id', $user->id)->update([
            'uploaded_bytes' => 700,
            'downloaded_bytes' => 1_000,
        ]);

        $allowed = app(DownloadEligibilityPolicy::class)->check($user, $torrent);
        $this->assertTrue($allowed['allowed']);
        $this->assertSame('ok', $allowed['reason']);
    }

    public function test_freeleech_and_no_history_behavior_follows_settings(): void
    {
        $user = User::factory()->create();
        $freeleechTorrent = Torrent::factory()->create(['is_freeleech' => true]);
        $normalTorrent = Torrent::factory()->create(['is_freeleech' => false]);

        UserStat::query()->create([
            'user_id' => $user->id,
            'uploaded_bytes' => 100,
            'downloaded_bytes' => 1_000,
        ]);

        $this->setRatioSettings([
            'enforcement_enabled' => true,
            'minimum_download_ratio' => 0.5,
            'freeleech_bypass_enabled' => true,
            'no_history_grace_enabled' => true,
        ]);

        $freeleechAllowed = app(DownloadEligibilityPolicy::class)->check($user, $freeleechTorrent);
        $this->assertTrue($freeleechAllowed['allowed']);
        $this->assertSame('freeleech', $freeleechAllowed['reason']);

        $this->setRatioSettings([
            'freeleech_bypass_enabled' => false,
        ]);

        $freeleechDenied = app(DownloadEligibilityPolicy::class)->check($user, $freeleechTorrent);
        $this->assertFalse($freeleechDenied['allowed']);
        $this->assertSame('ratio_too_low', $freeleechDenied['reason']);

        UserStat::query()->where('user_id', $user->id)->delete();

        $noHistoryAllowed = app(DownloadEligibilityPolicy::class)->check($user, $normalTorrent);
        $this->assertTrue($noHistoryAllowed['allowed']);
        $this->assertSame('no_history', $noHistoryAllowed['reason']);

        $this->setRatioSettings([
            'no_history_grace_enabled' => false,
        ]);

        $noHistoryDenied = app(DownloadEligibilityPolicy::class)->check($user, $normalTorrent);
        $this->assertFalse($noHistoryDenied['allowed']);
        $this->assertSame('ratio_too_low', $noHistoryDenied['reason']);
    }

    public function test_admin_ratio_settings_api_updates_values_and_applies_immediately(): void
    {
        Storage::fake('torrents');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create();
        $torrent = Torrent::factory()->create();

        UserStat::query()->create([
            'user_id' => $member->id,
            'uploaded_bytes' => 200,
            'downloaded_bytes' => 1_000,
        ]);

        $payload = app(BencodeService::class)->encode([
            'announce' => 'https://old.invalid/announce',
            'info' => ['name' => 'demo-file'],
        ]);
        Storage::disk('torrents')->put($torrent->torrentStoragePath(), $payload);

        $this->actingAs($admin)
            ->postJson('/api/admin/settings/tracker/ratio', [
                'enforcement_enabled' => true,
                'minimum_download_ratio' => 0.8,
                'freeleech_bypass_enabled' => false,
                'no_history_grace_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('minimum_download_ratio', 0.8);

        $this->actingAs($member)
            ->getJson('/api/torrents/'.$torrent->id.'/download')
            ->assertForbidden()
            ->assertJson([
                'message' => 'Download not allowed',
                'reason' => 'ratio_too_low',
            ]);
    }

    public function test_denied_download_logs_event_with_reason(): void
    {
        Storage::fake('torrents');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        UserStat::query()->create([
            'user_id' => $user->id,
            'uploaded_bytes' => 100,
            'downloaded_bytes' => 1_000,
        ]);

        $this->setRatioSettings([
            'enforcement_enabled' => true,
            'minimum_download_ratio' => 0.5,
            'freeleech_bypass_enabled' => false,
            'no_history_grace_enabled' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/api/torrents/'.$torrent->id.'/download')
            ->assertForbidden()
            ->assertJsonPath('reason', 'ratio_too_low');

        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'torrent.download.denied',
            'user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, bool|float>  $values
     */
    private function setRatioSettings(array $values): void
    {
        if (array_key_exists('enforcement_enabled', $values)) {
            SiteSetting::query()->where('key', RatioRulesConfig::ENFORCEMENT_ENABLED)->update([
                'value' => $values['enforcement_enabled'] ? 'true' : 'false',
            ]);
        }

        if (array_key_exists('minimum_download_ratio', $values)) {
            SiteSetting::query()->where('key', RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO)->update([
                'value' => (string) $values['minimum_download_ratio'],
            ]);
        }

        if (array_key_exists('freeleech_bypass_enabled', $values)) {
            SiteSetting::query()->where('key', RatioRulesConfig::FREELEECH_BYPASS_ENABLED)->update([
                'value' => $values['freeleech_bypass_enabled'] ? 'true' : 'false',
            ]);
        }

        if (array_key_exists('no_history_grace_enabled', $values)) {
            SiteSetting::query()->where('key', RatioRulesConfig::NO_HISTORY_GRACE_ENABLED)->update([
                'value' => $values['no_history_grace_enabled'] ? 'true' : 'false',
            ]);
        }
    }
}
