<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\Settings\TrackerRuntimeSettings;
use App\Services\Tracker\DownloadEligibilityPolicy;
use App\Tracker\Announce\AnnounceResponseBuilder;
use Tests\TestCase;

final class SysopSiteSettingsTest extends TestCase
{
    public function test_only_sysop_can_read_or_update_settings(): void
    {
        $this->get(route('sysop.settings.edit'))->assertRedirect(route('login'));
        $this->patch(route('sysop.settings.update'), $this->values())->assertRedirect(route('login'));

        foreach ([User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('sysop.settings.edit'))->assertForbidden();
            $this->actingAs($user)->patch(route('sysop.settings.update'), $this->values())->assertForbidden();
        }
    }

    public function test_settings_are_saved_audited_and_consumed_by_tracker_and_download_policy(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);
        $this->actingAs($sysop)->get(route('sysop.settings.edit'))->assertOk()->assertSee('Download ratio rules');
        $this->patch(route('sysop.settings.update'), $this->values())->assertRedirect(route('sysop.settings.edit'));

        $this->assertDatabaseHas('site_settings', ['key' => TrackerRuntimeSettings::ANNOUNCE_INTERVAL, 'value' => '300']);
        $audit = AuditLog::query()->where('action', 'sysop.site_settings.updated')->firstOrFail();
        $this->assertSame($sysop->id, $audit->user_id);
        $this->assertArrayHasKey('previous', $audit->metadata);
        $this->assertEquals(300, $audit->metadata['values']['announce_interval_seconds']);
        $this->assertSame('Update download and tracker rules for testing.', $audit->metadata['audit_reason']);

        $torrent = Torrent::factory()->create();
        Peer::factory()->for($torrent)->create(['is_seeder' => true, 'last_announce_at' => now()->subMinutes(20)]);
        $response = app(AnnounceResponseBuilder::class)->successWithoutPeers($torrent);
        $this->assertSame(300, $response->payload['interval']);
        $this->assertSame(0, $response->payload['complete']);
        $this->assertSame('enforcement_disabled', app(DownloadEligibilityPolicy::class)->check($sysop, $torrent)['reason']);

        $this->artisan('tracker:prune-ghost-peers')->assertSuccessful();
        $this->assertDatabaseMissing('peers', ['torrent_id' => $torrent->id]);

        $url = '/scrape/'.$sysop->ensurePasskey().'?info_hash='.urlencode(hex2bin($torrent->info_hash)).'&info_hash='.urlencode(hex2bin(str_repeat('a', 40)));
        $scrape = $this->get($url)->assertOk();
        $decoded = app(BencodeService::class)->decode($scrape->getContent());
        $this->assertSame('Too many info_hash values requested.', $decoded['failure reason']);
    }

    public function test_invalid_values_do_not_partially_save_or_write_an_audit(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);
        $this->actingAs($sysop);

        foreach ([
            ['announce_interval_seconds' => 0],
            ['scrape_max_info_hashes' => 201],
            ['minimum_download_ratio' => -1],
            ['ghost_peer_timeout_minutes' => 5],
        ] as $invalid) {
            $this->patch(route('sysop.settings.update'), array_replace($this->values(), $invalid))->assertSessionHasErrors();
        }

        $this->assertDatabaseMissing('site_settings', ['key' => TrackerRuntimeSettings::ANNOUNCE_INTERVAL]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sysop.site_settings.updated']);
    }

    public function test_site_settings_require_a_nonblank_audit_reason(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);
        $this->actingAs($sysop);

        $this->patch(route('sysop.settings.update'), array_replace($this->values(), [
            'audit_reason' => '   ',
        ]))->assertSessionHasErrors('audit_reason');

        $this->patch(route('sysop.settings.update'), array_replace($this->values(), [
            'audit_reason' => '',
        ]))->assertSessionHasErrors('audit_reason');

        $this->assertDatabaseMissing('site_settings', [
            'key' => TrackerRuntimeSettings::ANNOUNCE_INTERVAL,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'sysop.site_settings.updated',
        ]);
    }

    public function test_metadata_settings_script_survives_response_guard(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP, 'is_staff' => true]);
        $this->actingAs($sysop)->get(route('admin.settings.metadata.edit'))
            ->assertOk()
            ->assertSee('js/nextgn-metadata-settings.js')
            ->assertDontSee('const providerForm');
    }

    private function values(): array
    {
        return [
            'announce_interval_seconds' => 300,
            'ghost_peer_timeout_minutes' => 10,
            'scrape_max_info_hashes' => 1,
            'enforcement_enabled' => false,
            'minimum_download_ratio' => 0.75,
            'freeleech_bypass_enabled' => false,
            'no_history_grace_enabled' => false,
            'audit_reason' => 'Update download and tracker rules for testing.',
        ];
    }
}
