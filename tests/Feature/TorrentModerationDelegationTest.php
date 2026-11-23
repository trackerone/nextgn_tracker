<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\Moderation\TorrentModerationDelegation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentModerationDelegationTest extends TestCase
{
    use RefreshDatabase;

    private function updateTask(User $sysop, User $member, string $task, string $state = 'allow'): void
    {
        $this->actingAs($sysop)->post(route('sysop.permissions.moderation.update'), [
            'user_identifier' => (string) $member->getKey(),
            'task' => $task,
            'state' => $state,
            'audit_reason' => 'Assigned task as part of the reviewer rota.',
        ])->assertRedirect(route('sysop.permissions.index'));
    }

    public function test_review_only_delegation_shows_queue_but_no_mutation_or_staff_access(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $torrent = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING, 'is_approved' => false]);

        $this->actingAs($member)->get(route('staff.torrents.moderation.index'))->assertForbidden();
        $this->actingAs($member)->getJson(route('api.moderation.uploads.index'))->assertForbidden();

        $this->updateTask($sysop, $member, 'view');

        $this->actingAs($member)->get(route('home'))->assertOk()
            ->assertSee('Staff moderation')
            ->assertDontSee('Alpha intake')
            ->assertDontSee('User administration');

        $this->get(route('staff.torrents.moderation.index'))->assertOk()
            ->assertSee($torrent->name)
            ->assertDontSee('Approve and publish')
            ->assertDontSee('Reject with reason')
            ->assertDontSee('Soft-delete upload');
        $this->get(route('moderation.uploads'))->assertOk();
        $this->getJson(route('api.moderation.uploads.index'))->assertOk();
        $this->getJson(route('api.moderation.uploads.index', ['status' => Torrent::STATUS_REJECTED]))
            ->assertForbidden();

        $this->post(route('staff.torrents.approve', $torrent))->assertForbidden();
        $this->post(route('staff.torrents.reject', $torrent), ['reason' => 'No'])->assertForbidden();
        $this->post(route('staff.torrents.soft_delete', $torrent))->assertForbidden();
        $this->postJson(route('api.moderation.uploads.approve', $torrent))->assertForbidden();
        $this->postJson(route('api.moderation.uploads.reject', $torrent), ['reason' => 'No'])->assertForbidden();
        $this->get(route('staff.alpha-feedback.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->assertSame(Torrent::STATUS_PENDING, $torrent->fresh()->status->value);
    }

    public function test_approval_and_rejection_are_independent_across_web_and_api(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $approve = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING, 'is_approved' => false]);
        $reject = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING, 'is_approved' => false]);

        $this->updateTask($sysop, $member, 'approve');

        $this->actingAs($member)->get(route('staff.torrents.moderation.index'))->assertOk()
            ->assertSee('Approve and publish')
            ->assertDontSee('Reject with reason')
            ->assertDontSee('Soft-delete upload');

        $this->post(route('staff.torrents.reject', $reject), ['reason' => 'No'])->assertForbidden();
        $this->postJson(route('api.moderation.uploads.reject', $reject), ['reason' => 'No'])->assertForbidden();
        $this->postJson(route('api.moderation.uploads.approve', $approve))->assertOk();
        $this->assertTrue($approve->fresh()->isApproved());
        $this->assertDatabaseHas('audit_logs', ['action' => 'torrent.approved', 'target_id' => $approve->id]);

        $this->updateTask($sysop, $member, 'approve', 'inherit');
        $this->updateTask($sysop, $member, 'reject');

        $this->actingAs($member)->get(route('staff.torrents.moderation.index'))->assertOk()
            ->assertDontSee('Approve and publish')
            ->assertSee('Reject with reason');
        $this->post(route('staff.torrents.approve', $reject))->assertForbidden();
        $this->postJson(route('api.moderation.uploads.approve', $reject))->assertForbidden();
        $this->post(route('staff.torrents.reject', $reject), ['reason' => 'Missing required metadata'])
            ->assertRedirect(route('staff.torrents.moderation.index'));
        $this->assertTrue($reject->fresh()->isRejected());

        $this->assertDatabaseHas('audit_logs', ['action' => 'torrent.rejected', 'target_id' => $reject->id]);
    }

    public function test_revocation_is_immediate_and_audited(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $pending = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING]);

        $this->updateTask($sysop, $member, 'approve');
        $this->assertTrue(TorrentModerationDelegation::canApprove($member));
        $this->actingAs($member)->get(route('staff.torrents.moderation.index'))->assertOk();

        $this->updateTask($sysop, $member, 'approve', 'inherit');
        $this->assertFalse(TorrentModerationDelegation::canApprove($member));
        $this->assertFalse(TorrentModerationDelegation::canView($member));
        $this->actingAs($member)->get(route('staff.torrents.moderation.index'))->assertForbidden();
        $this->post(route('staff.torrents.approve', $pending))->assertForbidden();
        $this->getJson(route('api.moderation.uploads.index'))->assertForbidden();

        $this->assertDatabaseMissing('user_permission_overrides', [
            'user_id' => $member->id,
            'permission' => TorrentModerationDelegation::TASKS['approve'],
        ]);
        $audit = AuditLog::query()->where('action', 'sysop.permissions.moderation_delegation_updated')
            ->latest('id')->firstOrFail();
        $this->assertSame('inherit', $audit->metadata['new_state']);
        $this->assertSame($sysop->id, $audit->user_id);
        $this->assertSame($member->id, $audit->target_id);
    }

    public function test_only_sysop_can_delegate_and_existing_feature_matrix_cannot_inject_staff_tasks(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $payload = [
            'user_identifier' => (string) $member->id,
            'task' => 'approve',
            'state' => 'allow',
            'audit_reason' => 'Review delegation.',
        ];

        $this->post(route('sysop.permissions.moderation.update'), $payload)->assertRedirect(route('login'));
        foreach ([$member, $moderator, $admin] as $actor) {
            $this->actingAs($actor)->post(route('sysop.permissions.moderation.update'), $payload)->assertForbidden();
        }
        $this->actingAs($sysop)->post(route('sysop.permissions.users.update'), [
            'user_identifier' => (string) $member->id,
            'feature' => 'moderation.torrents.approve',
            'state' => 'allow',
            'audit_reason' => 'Invalid feature injection.',
        ])->assertSessionHasErrors('feature');
        $this->post(route('sysop.permissions.moderation.update'), [
            ...$payload,
            'task' => 'soft_delete',
        ])->assertSessionHasErrors('task');
        $this->post(route('sysop.permissions.moderation.update'), [
            ...$payload,
            'user_identifier' => (string) $moderator->id,
        ])->assertForbidden();
        $this->post(route('sysop.permissions.moderation.update'), [
            ...$payload,
            'user_identifier' => (string) $sysop->id,
        ])->assertForbidden();
        $this->assertDatabaseCount('user_permission_overrides', 0);
    }

    public function test_existing_staff_powers_and_audit_remain_intact(): void
    {
        foreach ([User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SYSOP] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->assertTrue(TorrentModerationDelegation::canView($staff));
            $this->assertTrue(TorrentModerationDelegation::canApprove($staff));
            $this->assertTrue(TorrentModerationDelegation::canReject($staff));

            $pending = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING]);
            $this->actingAs($staff)->post(route('staff.torrents.soft_delete', $pending))
                ->assertRedirect(route('staff.torrents.moderation.index'));
            $this->assertTrue($pending->fresh()->isSoftDeleted());
        }
        $this->assertSame(3, AuditLog::query()->where('action', 'torrent.soft_deleted')->count());
    }

    public function test_staff_demotion_revokes_previous_delegation_in_same_transaction(): void
    {
        $this->seed(RoleSeeder::class);
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        $this->updateTask($sysop, $member, 'approve');
        $this->actingAs($sysop)->patch(route('admin.users.role.update', $member), [
            'role' => User::ROLE_MODERATOR,
            'audit_reason' => 'Temporary staff assignment.',
        ])->assertRedirect();

        $this->patch(route('admin.users.role.update', $member), [
            'role' => User::ROLE_USER,
            'audit_reason' => 'End of staff assignment.',
        ])->assertRedirect();

        $this->assertDatabaseMissing('user_permission_overrides', [
            'user_id' => $member->id,
            'permission' => TorrentModerationDelegation::TASKS['approve'],
        ]);
        $this->assertFalse(TorrentModerationDelegation::canView($member->fresh()));
        $this->actingAs($member)->get(route('staff.torrents.moderation.index'))->assertForbidden();
        $audit = AuditLog::query()->where('action', 'user.role_changed')->latest('id')->firstOrFail();
        $this->assertSame(1, $audit->metadata['revoked_moderation_delegations']);
    }

    public function test_disabled_or_banned_members_cannot_exercise_delegation(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        UserPermissionOverride::query()->create([
            'user_id' => $member->id,
            'permission' => TorrentModerationDelegation::TASKS['approve'],
            'allowed' => true,
        ]);

        $member->forceFill(['is_disabled' => true])->save();
        $this->assertFalse(TorrentModerationDelegation::canView($member));
        $this->assertFalse(TorrentModerationDelegation::canApprove($member));

        $member->forceFill(['is_disabled' => false, 'is_banned' => true])->save();
        $this->assertFalse(TorrentModerationDelegation::canView($member));
        $this->assertFalse(TorrentModerationDelegation::canApprove($member));
    }
}
