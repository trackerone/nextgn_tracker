<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\MatchTorrentWatchPresets;
use App\Models\NotificationWatchPreset;
use App\Models\RssFeedPreset;
use App\Models\Torrent;
use App\Models\TorrentWatchNotification;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\Notifications\TorrentWatchPresetMatcher;
use App\Services\PermissionService;
use App\Services\Settings\SiteSettingsRepository;
use Tests\TestCase;

final class MemberContentPermissionsTest extends TestCase
{
    public function test_existing_member_classes_keep_their_browse_download_rss_and_watch_access_by_default(): void
    {
        foreach (['newbie', User::ROLE_USER, User::ROLE_POWER_USER, User::ROLE_UPLOADER, User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SYSOP] as $role) {
            $member = User::factory()->create(['role' => $role]);

            foreach (['torrent.browse', 'torrent.download', 'rss.access', 'watch.access'] as $permission) {
                self::assertTrue(PermissionService::allow($member, $permission), $role.' should retain '.$permission);
            }
        }
    }

    public function test_revoked_browse_blocks_web_api_discovery_rss_and_watch_even_with_valid_token(): void
    {
        $member = $this->tokenUser();
        $torrent = Torrent::factory()->create(['name' => 'Private Denied Browse Release']);
        $preset = RssFeedPreset::factory()->create(['user_id' => $member->id]);

        $this->deny($member, 'torrent.browse');

        self::assertFalse(PermissionService::allow($member, 'torrent.download'));
        self::assertFalse(PermissionService::allow($member, 'rss.access'));
        self::assertFalse(PermissionService::allow($member, 'watch.access'));

        $this->actingAs($member)
            ->get(route('home'))->assertOk()
            ->assertDontSee('Private Denied Browse Release')
            ->assertDontSee('>Browse</a>', false);

        $this->get(route('torrents.index'))->assertForbidden();
        $this->get(route('torrents.show', $torrent))->assertForbidden();
        $this->get(route('torrents.download', $torrent))->assertForbidden();
        $this->get(route('my.follows'))->assertForbidden();
        $this->get(route('account.saved-intents.index'))->assertForbidden();
        $this->get(route('my.watch-center'))->assertForbidden();
        $this->get(route('account.watch-presets.index'))->assertForbidden();
        $this->get(route('account.rss.index'))->assertForbidden();

        $this->getJson(route('api.torrents.index'))->assertForbidden();
        $this->getJson(route('api.torrents.show', $torrent))->assertForbidden();
        $this->getJson(route('api.torrents.download', $torrent))->assertForbidden();
        $this->getJson(route('api.discovery.home'))->assertForbidden();
        $this->getJson(route('api.discovery.trending'))->assertForbidden();
        $this->getJson(route('api.discovery.rss-suggestions'))->assertForbidden();
        $this->getJson(route('api.discovery.watch-preset-suggestions'))->assertForbidden();

        $this->get(route('rss.feed', ['token' => $member->rss_token]))->assertNotFound();
        $this->get(route('rss.presets.feed', [
            'token' => $member->rss_token,
            'preset' => $preset->public_id,
        ]))->assertNotFound();
        $this->get(route('rss.torrents.download', [
            'token' => $member->rss_token,
            'torrent' => $torrent->id,
        ]))->assertNotFound();

        // A server-side policy call cannot bypass the route restrictions.
        self::assertFalse(app(\App\Policies\TorrentPolicy::class)->view($member, $torrent)->allowed());
        self::assertFalse(app(\App\Policies\TorrentPolicy::class)->download($member, $torrent)->allowed());
    }

    public function test_download_deny_keeps_browse_but_hides_download_and_denies_rss_and_watch(): void
    {
        $member = $this->tokenUser();
        $torrent = Torrent::factory()->create(['name' => 'Visible Yet Download Restricted']);
        $this->deny($member, 'torrent.download');

        $this->actingAs($member)->get(route('torrents.index'))
            ->assertOk()->assertSee('Visible Yet Download Restricted')
            ->assertDontSee('aria-label="Download Visible Yet Download Restricted"', false);

        $this->get(route('torrents.show', $torrent))
            ->assertOk()->assertSee('Download is currently blocked')
            ->assertSee('restricted by account permissions');

        $this->getJson(route('api.torrents.index'))->assertOk();
        $this->get(route('torrents.download', $torrent))->assertForbidden();
        $this->getJson(route('api.torrents.download', $torrent))->assertForbidden();
        $this->get(route('rss.feed', ['token' => $member->rss_token]))->assertNotFound();
        $this->get(route('my.watch-center'))->assertForbidden();

        // Explicitly allowing RSS cannot override its download prerequisite.
        app(SiteSettingsRepository::class)->set('access.roles.user', [
            'torrent.browse' => true,
            'torrent.download' => false,
            'rss.access' => true,
            'watch.access' => true,
        ], 'json');
        self::assertFalse(PermissionService::allow($member, 'rss.access'));
        self::assertFalse(PermissionService::allow($member, 'watch.access'));
    }

    public function test_rss_deny_revokes_token_feeds_presets_download_and_suggestions_only(): void
    {
        $member = $this->tokenUser();
        $preset = RssFeedPreset::factory()->create(['user_id' => $member->id, 'name' => 'Hidden RSS preset']);
        $torrent = Torrent::factory()->create();

        $this->deny($member, 'rss.access');

        $this->actingAs($member)->get(route('torrents.index'))->assertOk()
            ->assertDontSee('RSS for this search');
        $this->get(route('account.rss.index'))->assertForbidden();
        $this->post(route('account.rss.rotate'))->assertForbidden();
        $this->post(route('account.rss.presets.store'))->assertForbidden();
        $this->getJson(route('api.discovery.rss-suggestions'))->assertForbidden();

        $this->get(route('rss.feed', ['token' => $member->rss_token]))->assertNotFound();
        $this->get(route('rss.presets.feed', [
            'token' => $member->rss_token, 'preset' => $preset->public_id,
        ]))->assertNotFound();
        $this->get(route('rss.torrents.download', [
            'token' => $member->rss_token, 'torrent' => $torrent->id,
        ]))->assertNotFound();

        $this->get(route('my.watch-center'))->assertOk()->assertDontSee('Hidden RSS preset');
        self::assertTrue(PermissionService::allow($member, 'torrent.download'));
        self::assertTrue(PermissionService::allow($member, 'watch.access'));
    }

    public function test_watch_deny_blocks_presets_and_background_matches_and_hides_old_alerts(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $publisher = User::factory()->create(['role' => User::ROLE_UPLOADER]);
        $torrent = Torrent::factory()->create(['user_id' => $publisher->id]);
        $preset = NotificationWatchPreset::factory()->create([
            'user_id' => $member->id,
            'filters' => [],
        ]);
        $oldAlert = TorrentWatchNotification::factory()->create([
            'user_id' => $member->id,
            'torrent_id' => $torrent->id,
            'notification_watch_preset_id' => $preset->id,
            'title' => 'Hidden historical alert name',
        ]);

        $this->deny($member, 'watch.access');

        $this->actingAs($member)->get(route('my.watch-center'))->assertForbidden();
        $this->get(route('account.watch-presets.index'))->assertForbidden();
        $this->post(route('account.watch-presets.store'))->assertForbidden();
        $this->getJson(route('api.discovery.watch-preset-suggestions'))->assertForbidden();

        $this->get(route('account.notifications.index'))->assertOk()
            ->assertDontSee('Hidden historical alert name')
            ->assertSee('0 search alerts');
        $this->post(route('account.notifications.read', ['notification' => $oldAlert]))->assertNotFound();

        // Queued work reevaluates the permission when executed, not at publish time.
        (new MatchTorrentWatchPresets((int) $torrent->id))->handle(app(TorrentWatchPresetMatcher::class));
        $this->assertDatabaseCount('torrent_watch_notifications', 1);
        self::assertNull($oldAlert->fresh()->read_at);

        $this->post(route('account.notifications.read_all'))->assertRedirect();
        self::assertNull($oldAlert->fresh()->read_at);
    }

    public function test_sysop_role_and_member_overrides_take_effect_without_overwriting_missing_new_keys(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($sysop)->patch(route('sysop.permissions.roles.update'), [
            'role' => 'user',
            'permissions' => [
                'upload' => '0', 'forum' => '1', 'messages' => '1', 'invitations' => '0',
                'browse' => '1', 'download' => '0', 'rss' => '1', 'watch' => '1',
            ],
            'audit_reason' => 'Restrict downloads for members during a review.',
        ])->assertRedirect(route('sysop.permissions.index'));

        self::assertTrue(PermissionService::allow($member, 'torrent.browse'));
        self::assertFalse(PermissionService::allow($member, 'torrent.download'));
        self::assertFalse(PermissionService::allow($member, 'rss.access'));

        $this->actingAs($sysop)->post(route('sysop.permissions.users.update'), [
            'user_identifier' => (string) $member->id,
            'feature' => 'download',
            'state' => 'allow',
            'audit_reason' => 'Allow selected member to download.',
        ])->assertRedirect(route('sysop.permissions.index'));

        self::assertTrue(PermissionService::allow($member, 'torrent.download'));
        self::assertTrue(PermissionService::allow($member, 'rss.access'));

        // A legacy four-checkbox POST must not unexpectedly disable new features.
        $this->patch(route('sysop.permissions.roles.update'), [
            'role' => 'user',
            'permissions' => ['upload' => '0', 'forum' => '1', 'messages' => '1', 'invitations' => '0'],
            'audit_reason' => 'Legacy role permission form.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $settings = app(SiteSettingsRepository::class)->getJson('access.roles.user');
        self::assertTrue($settings['torrent.browse']);
        self::assertFalse($settings['torrent.download']);
        self::assertTrue($settings['rss.access']);
        self::assertTrue($settings['watch.access']);

        // The owner's control plane cannot be restricted by member overrides.
        self::assertTrue(PermissionService::allow($sysop, 'torrent.browse'));
        self::assertTrue(PermissionService::allow($sysop, 'torrent.download'));
    }

    private function tokenUser(): User
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $member->rotateRssToken();

        return $member->refresh();
    }

    private function deny(User $user, string $permission): void
    {
        UserPermissionOverride::query()->updateOrCreate(
            ['user_id' => $user->id, 'permission' => $permission],
            ['allowed' => false],
        );
    }
}
