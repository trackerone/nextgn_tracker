<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserNameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_username_is_read_only_and_cannot_be_changed_using_profile_update(): void
    {
        foreach ([User::ROLE_USER, User::ROLE_ADMIN, User::ROLE_SYSOP] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'password' => Hash::make('OldPassword123!'),
            ]);
            $oldName = $user->name;

            $this->actingAs($user)->get(route('account.settings.edit'))
                ->assertOk()
                ->assertSee('Username (locked)')
                ->assertSee('readonly')
                ->assertDontSee('name="name"', false);

            $this->patch(route('account.settings.update'), [
                'name' => 'Unauthorized Rename '.$user->id,
                'email' => 'update-'.$user->id.'@example.test',
                'current_password' => 'OldPassword123!',
            ])->assertSessionHasErrors('name');

            $user->refresh();
            $this->assertSame($oldName, $user->name);
            $this->assertNotSame('update-'.$user->id.'@example.test', $user->email);
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.username_changed']);
    }

    public function test_only_admin_and_sysop_can_open_user_administration(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));

        $target = User::factory()->create();
        foreach ([User::ROLE_USER, User::ROLE_UPLOADER, User::ROLE_MODERATOR] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('admin.users.index'))->assertForbidden();
            $this->patch(route('admin.users.name.update', $target), [
                'name' => 'Unauthorized Change',
                'audit_reason' => 'No permission.',
            ])->assertForbidden();
        }

        foreach ([User::ROLE_ADMIN, User::ROLE_SYSOP] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('admin.users.index'))
                ->assertOk()
                ->assertSee('User administration');
        }

        $this->assertNotSame('Unauthorized Change', $target->fresh()->name);
    }

    public function test_admin_can_rename_regular_member_with_reason_and_audit_log(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $oldName = $member->name;
        $slug = $member->public_slug;

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['user' => $member->id]))
            ->assertOk()
            ->assertSee($oldName)
            ->assertSee('Save username change');

        $this->patch(route('admin.users.name.update', $member), [
            'name' => 'Approved New Username',
            'audit_reason' => 'Member requested the change in a support ticket.',
        ])->assertRedirect(route('admin.users.index', ['user' => $member->id]));

        $member->refresh();
        $this->assertSame('Approved New Username', $member->name);
        $this->assertSame($slug, $member->public_slug);
        $this->assertSame(User::ROLE_USER, $member->role);

        $log = AuditLog::query()->where('action', 'user.username_changed')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($member->id, $log->target_id);
        $this->assertSame([
            'actor_user_id' => $admin->id,
            'target_user_id' => $member->id,
            'old_name' => $oldName,
            'new_name' => 'Approved New Username',
            'audit_reason' => 'Member requested the change in a support ticket.',
        ], $log->metadata);
    }

    public function test_admin_cannot_rename_another_admin_or_sysop_but_sysop_can(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $adminTarget = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $sysopTarget = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->actingAs($admin)
            ->patch(route('admin.users.name.update', $adminTarget), [
                'name' => 'Blocked Admin Rename',
                'audit_reason' => 'Not allowed.',
            ])->assertForbidden();

        $this->patch(route('admin.users.name.update', $sysopTarget), [
            'name' => 'Blocked Sysop Rename',
            'audit_reason' => 'Not allowed.',
        ])->assertForbidden();

        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)
            ->patch(route('admin.users.name.update', $adminTarget), [
                'name' => 'New Administrator Name',
                'audit_reason' => 'Administrator requested renaming.',
            ])->assertRedirect();

        $this->assertSame('New Administrator Name', $adminTarget->fresh()->name);
        $this->assertNotSame('Blocked Sysop Rename', $sysopTarget->fresh()->name);
    }

    public function test_even_admin_and_sysop_cannot_rename_themselves(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_SYSOP] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $original = $actor->name;
            $this->actingAs($actor)
                ->patch(route('admin.users.name.update', $actor), [
                    'name' => 'Self Rename Attempt',
                    'audit_reason' => 'Self service.',
                ])->assertForbidden();
            $this->assertSame($original, $actor->fresh()->name);
        }
    }

    public function test_blank_reason_unchanged_name_and_duplicate_username_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $other = User::factory()->create(['name' => 'Already Taken Name']);
        $this->actingAs($admin);

        $this->patch(route('admin.users.name.update', $member), [
            'name' => 'New Username',
            'audit_reason' => '  ',
        ])->assertSessionHasErrors('audit_reason');

        $this->patch(route('admin.users.name.update', $member), [
            'name' => $member->name,
            'audit_reason' => 'Valid reason.',
        ])->assertSessionHasErrors('name');

        $this->patch(route('admin.users.name.update', $member), [
            'name' => $other->name,
            'audit_reason' => 'Valid reason.',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('audit_logs', 0);
    }
}
