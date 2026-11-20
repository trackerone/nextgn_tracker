<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SysopPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sysop_can_manage_permissions(): void
    {
        $this->get(route('sysop.permissions.index'))->assertRedirect(route('login'));
        $this->patch(route('sysop.permissions.roles.update'), $this->rolePayload())->assertRedirect(route('login'));

        foreach ([User::ROLE_USER, User::ROLE_UPLOADER, User::ROLE_MODERATOR, User::ROLE_ADMIN] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('sysop.permissions.index'))->assertForbidden();
            $this->patch(route('sysop.permissions.roles.update'), $this->rolePayload())->assertForbidden();
            $this->post(route('sysop.permissions.users.update'), $this->userPayload((string) $actor->id))->assertForbidden();
        }

        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->get(route('sysop.permissions.index'))
            ->assertOk()
            ->assertSee('Individual exceptions')
            ->assertSee('Role defaults');
    }

    public function test_new_member_cannot_see_or_access_upload_but_uploader_can(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($member)->get(route('home'))->assertOk()->assertDontSee('>Upload release<', false);
        $this->get(route('torrents.index'))->assertOk()->assertDontSee('>Upload a release<', false);
        $this->get(route('torrents.upload'))->assertForbidden();
        $this->post(route('torrents.store'))->assertForbidden();
        $this->postJson(route('api.uploads.store'))->assertForbidden();
        $this->assertFalse(PermissionService::allow($member, 'torrent.upload'));

        $uploader = User::factory()->create(['role' => User::ROLE_UPLOADER]);
        $this->actingAs($uploader)->get(route('torrents.upload'))->assertOk();
        $this->assertTrue(PermissionService::allow($uploader, 'torrent.upload'));
    }

    public function test_role_change_updates_navigation_and_enforced_routes_with_audit(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($member)->get(route('topics.index'))->assertOk();
        $this->get(route('pm.index'))->assertOk();

        $this->actingAs($sysop)->patch(route('sysop.permissions.roles.update'), [
            'role' => 'user',
            'permissions' => ['upload' => '1', 'forum' => '0', 'messages' => '0'],
            'audit_reason' => 'Enable trusted uploads while hiding community features.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $this->assertDatabaseHas('site_settings', ['key' => 'access.roles.user']);
        $audit = AuditLog::query()->where('action', 'sysop.permissions.role_updated')->firstOrFail();
        $this->assertSame('user', $audit->metadata['role']);
        $this->assertSame($sysop->id, $audit->user_id);

        $this->actingAs($member)->get(route('torrents.upload'))->assertOk();
        $this->get(route('topics.index'))->assertForbidden();
        $this->get(route('pm.index'))->assertForbidden();
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('>Upload<', false)
            ->assertDontSee('>Forum<', false)
            ->assertDontSee('>Messages<', false);
    }

    public function test_individual_exception_can_allow_deny_and_inherit(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($sysop)
            ->post(route('sysop.permissions.users.update'), $this->userPayload((string) $member->id, 'allow'))
            ->assertRedirect(route('sysop.permissions.index'));

        $override = UserPermissionOverride::query()->where('user_id', $member->id)->firstOrFail();
        $this->assertTrue($override->allowed);
        $this->actingAs($member)->get(route('torrents.upload'))->assertOk();

        $this->actingAs($sysop)
            ->post(route('sysop.permissions.users.update'), $this->userPayload((string) $member->id, 'deny'))
            ->assertRedirect(route('sysop.permissions.index'));
        $this->actingAs($member)->get(route('torrents.upload'))->assertForbidden();

        $this->actingAs($sysop)
            ->post(route('sysop.permissions.users.update'), $this->userPayload((string) $member->id, 'inherit'))
            ->assertRedirect(route('sysop.permissions.index'));
        $this->assertDatabaseMissing('user_permission_overrides', ['user_id' => $member->id, 'permission' => 'torrent.upload']);
        $this->assertDatabaseCount('audit_logs', 3);
        $this->actingAs($member)->get(route('torrents.upload'))->assertForbidden();
    }

    public function test_protected_privileges_are_not_edited_or_granted(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($sysop);

        $this->patch(route('sysop.permissions.roles.update'), [
            'role' => 'sysop',
            'permissions' => ['upload' => '0', 'forum' => '0', 'messages' => '0'],
            'audit_reason' => 'Invalid sysop edit',
        ])->assertSessionHasErrors('role');

        $this->patch(route('sysop.permissions.roles.update'), [
            'role' => 'user',
            'permissions' => ['upload' => '0', 'forum' => '1', 'messages' => '1', 'admin' => '1'],
            'audit_reason' => 'Invalid privilege injection',
        ])->assertSessionHasErrors();

        $this->post(route('sysop.permissions.users.update'), $this->userPayload((string) $sysop->id))
            ->assertForbidden();

        $this->post(route('sysop.permissions.users.update'), [
            'user_identifier' => (string) $member->id,
            'feature' => 'admin.access',
            'state' => 'allow',
            'audit_reason' => 'Invalid privilege injection',
        ])->assertSessionHasErrors('feature');

        $this->assertTrue(PermissionService::allow($sysop, 'torrent.upload'));
        $this->assertFalse(PermissionService::allow($member, 'torrent.upload'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function rolePayload(): array
    {
        return [
            'role' => 'user',
            'permissions' => ['upload' => '0', 'forum' => '1', 'messages' => '1'],
            'audit_reason' => 'Routine permissions review.',
        ];
    }

    private function userPayload(string $identifier, string $state = 'allow'): array
    {
        return [
            'user_identifier' => $identifier,
            'feature' => 'upload',
            'state' => $state,
            'audit_reason' => 'Test temporary permission exception.',
        ];
    }
}
