<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invite;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvitationIssuancePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_members_cannot_issue_invites_by_default_or_enter_staff_history(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        $this->assertFalse(PermissionService::allow($member, 'invites.create'));

        $this->actingAs($member)->get(route('account.invites'))
            ->assertOk()
            ->assertDontSee('Create invitation');

        $this->post(route('account.invites.store'), ['notes' => 'Unauthorized'])->assertForbidden();
        $this->post(route('admin.invites.store'), ['max_uses' => 1])->assertForbidden();
        $this->get(route('admin.invites.index'))->assertForbidden();

        $this->assertDatabaseCount('invites', 0);
    }

    public function test_sysop_can_grant_member_class_invites_without_granting_staff_access(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $other = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $otherInvite = Invite::factory()->create([
            'inviter_user_id' => $other->id,
            'code' => 'OTHER-INVITATION-SECRET',
        ]);

        $this->actingAs($sysop)->patch(route('sysop.permissions.roles.update'), [
            'role' => 'user',
            'permissions' => [
                'upload' => '0',
                'forum' => '1',
                'messages' => '1',
                'invitations' => '1',
            ],
            'audit_reason' => 'Allow members to invite trusted contacts.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $this->assertTrue(PermissionService::allow($member, 'invites.create'));

        $this->actingAs($member)->get(route('account.invites'))
            ->assertOk()
            ->assertSee('Create invitation')
            ->assertDontSee($otherInvite->code);

        // Delegated invitations are single-use irrespective of client-submitted values.
        $this->post(route('account.invites.store'), [
            'notes' => 'Known member',
            'max_uses' => 10000,
            'expires_at' => now()->addYears(20)->toDateTimeString(),
        ])->assertRedirect(route('account.invites'));

        $issued = Invite::query()->where('inviter_user_id', $member->id)->firstOrFail();
        $this->assertSame(1, $issued->max_uses);
        $this->assertNotNull($issued->expires_at);
        $this->assertTrue($issued->expires_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertSame('Known member', $issued->notes);

        $this->actingAs($member)->get(route('account.invites'))->assertSee($issued->code)
            ->assertDontSee($otherInvite->code);
        $this->get(route('admin.invites.index'))->assertForbidden();
        $this->get(route('sysop.permissions.index'))->assertForbidden();

        $audit = AuditLog::query()->where('action', 'invites.issued')->firstOrFail();
        $this->assertSame($member->id, $audit->user_id);
        $this->assertSame($issued->id, $audit->metadata['invite_id']);
        $this->assertStringNotContainsString($issued->code, json_encode($audit->metadata));

        $this->actingAs($sysop)->patch(route('sysop.permissions.roles.update'), [
            'role' => 'user',
            'permissions' => [
                'upload' => '0',
                'forum' => '1',
                'messages' => '1',
                'invitations' => '0',
            ],
            'audit_reason' => 'Pause invites for ordinary members.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $this->assertFalse(PermissionService::allow($member, 'invites.create'));
        $this->actingAs($member)->get(route('account.invites'))
            ->assertOk()
            ->assertDontSee('Create invitation')
            ->assertSee($issued->code);
        $this->post(route('account.invites.store'))->assertForbidden();
        $this->assertDatabaseCount('invites', 2);
    }

    public function test_individual_override_can_allow_then_deny_invitations(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($sysop)->post(route('sysop.permissions.users.update'), [
            'user_identifier' => (string) $member->id,
            'feature' => 'invitations',
            'state' => 'allow',
            'audit_reason' => 'Authorize a specific trusted inviter.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $this->actingAs($member)->post(route('account.invites.store'))
            ->assertRedirect(route('account.invites'));
        $this->assertDatabaseHas('invites', ['inviter_user_id' => $member->id]);

        $this->actingAs($sysop)->post(route('sysop.permissions.users.update'), [
            'user_identifier' => (string) $member->id,
            'feature' => 'invitations',
            'state' => 'deny',
            'audit_reason' => 'Revoke this user invitation privileges.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $this->actingAs($member)->post(route('account.invites.store'))->assertForbidden();
        $this->assertDatabaseCount('invites', 1);
    }

    public function test_staff_can_keep_invitation_history_but_sysop_can_remove_issuance_permission(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);

        $this->assertTrue(PermissionService::allow($moderator, 'invites.create'));
        $this->actingAs($moderator)->post(route('admin.invites.store'), [
            'max_uses' => 2,
            'notes' => 'Moderator invitation',
        ])->assertRedirect(route('admin.invites.index'));
        $this->assertDatabaseHas('invites', ['max_uses' => 2, 'inviter_user_id' => $moderator->id]);

        $this->actingAs($sysop)->patch(route('sysop.permissions.roles.update'), [
            'role' => 'moderator',
            'permissions' => [
                'upload' => '1',
                'forum' => '1',
                'messages' => '1',
                'invitations' => '0',
            ],
            'audit_reason' => 'Restrict moderation team to review existing invitations.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $this->actingAs($moderator)->get(route('admin.invites.index'))
            ->assertOk()
            ->assertDontSee('Generate invite');
        $this->post(route('admin.invites.store'), ['max_uses' => 1])->assertForbidden();
        $this->post(route('account.invites.store'))->assertForbidden();
        $this->assertDatabaseCount('invites', 1);
    }

    public function test_banned_member_override_does_not_bypass_account_restrictions(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($sysop)->post(route('sysop.permissions.users.update'), [
            'user_identifier' => (string) $member->id,
            'feature' => 'invitations',
            'state' => 'allow',
            'audit_reason' => 'Temporary issue permission.',
        ])->assertRedirect();

        $member->forceFill(['is_disabled' => true])->save();

        $this->assertFalse(PermissionService::allow($member->fresh(), 'invites.create'));
        $this->actingAs($member)->post(route('account.invites.store'))->assertForbidden();
        $this->assertDatabaseCount('invites', 0);
    }
}
