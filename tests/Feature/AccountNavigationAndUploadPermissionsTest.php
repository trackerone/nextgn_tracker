<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountNavigationAndUploadPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_navigation_is_present_once_and_above_content_on_every_personal_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($user);

        $routes = [
            'account.index',
            'account.hnr',
            'account.settings.edit',
            'account.access',
            'account.rss.index',
            'account.people',
            'account.snatches',
            'account.notifications.index',
            'account.invites',
            'my.follows',
            'my.watch-center',
            'pm.index',
        ];

        foreach ($routes as $route) {
            $response = $this->get(route($route))->assertOk();
            $html = (string) $response->getContent();
            $menuPosition = strpos($html, 'aria-label="Account navigation"');
            $headingPosition = strpos($html, '<h1');

            $this->assertNotFalse($menuPosition, 'Missing account navigation on '.$route);
            $this->assertNotFalse($headingPosition, 'Missing page heading on '.$route);
            $this->assertLessThan($headingPosition, $menuPosition, 'Account navigation should precede the heading on '.$route);
            $this->assertSame(1, substr_count($html, 'aria-label="Account navigation"'), 'Duplicate account navigation on '.$route);
            $response->assertSee('href="'.route('account.index').'"', false);
        }

        $this->get(route('torrents.index'))->assertOk()->assertDontSee('aria-label="Account navigation"', false);
    }

    public function test_regular_member_cannot_open_upload_history_or_submit_torrents(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($member);

        $this->get(route('account.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('my.uploads').'"', false)
            ->assertDontSee('>My uploads<', false);
        $this->get(route('account.invites'))
            ->assertOk()
            ->assertDontSee('href="'.route('my.uploads').'"', false);
        $this->get(route('my.uploads'))->assertForbidden();
        $this->get(route('torrents.upload'))->assertForbidden();
        $this->post(route('torrents.store'))->assertForbidden();
        $this->postJson(route('api.uploads.store'))->assertForbidden();
    }

    public function test_uploader_sees_history_navigation_and_mobile_safe_empty_state(): void
    {
        $uploader = User::factory()->create(['role' => User::ROLE_UPLOADER]);
        $this->actingAs($uploader);

        $this->get(route('account.index'))
            ->assertOk()
            ->assertSee('href="'.route('my.uploads').'"', false);

        $response = $this->get(route('my.uploads'))
            ->assertOk()
            ->assertSee('aria-label="Account navigation"', false)
            ->assertSee('No uploads submitted yet')
            ->assertSee('Upload your first torrent')
            ->assertDontSee('colspan="4"', false);

        $html = (string) $response->getContent();
        $this->assertLessThan(strpos($html, '<h1'), strpos($html, 'aria-label="Account navigation"'));
    }

    public function test_sysop_individual_override_controls_upload_menu_and_protected_route(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        UserPermissionOverride::query()->create([
            'user_id' => $member->id,
            'permission' => 'torrent.upload',
            'allowed' => true,
        ]);

        $this->actingAs($member)->get(route('my.uploads'))->assertOk();
        $this->get(route('account.index'))->assertOk()->assertSee('href="'.route('my.uploads').'"', false);

        UserPermissionOverride::query()
            ->where('user_id', $member->id)
            ->where('permission', 'torrent.upload')
            ->update(['allowed' => false]);

        $this->get(route('account.index'))->assertOk()->assertDontSee('href="'.route('my.uploads').'"', false);
        $this->get(route('my.uploads'))->assertForbidden();
    }

    public function test_disabled_messages_permission_hides_tab_and_denies_direct_access(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        UserPermissionOverride::query()->create([
            'user_id' => $member->id,
            'permission' => 'messages.access',
            'allowed' => false,
        ]);

        $this->actingAs($member)->get(route('account.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('pm.index').'"', false);
        $this->get(route('pm.index'))->assertForbidden();
    }
}
