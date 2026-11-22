<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents admin from promoting a user to sysop', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($admin)
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_SYSOP,
            'audit_reason' => 'Emergency access review.',
        ])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe(User::ROLE_USER);
});

it('prevents admin from demoting a sysop', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_SYSOP]);

    $this->actingAs($admin)
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_ADMIN,
            'audit_reason' => 'Access review.',
        ])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe(User::ROLE_SYSOP);
});

it('allows sysop to promote a user to sysop', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $target = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($sysop)
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_SYSOP,
            'audit_reason' => 'Approved sysop promotion.',
        ])
        ->assertRedirect();

    expect($target->fresh()->role)->toBe(User::ROLE_SYSOP);
});

it('allows sysop to demote another sysop', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $target = User::factory()->create(['role' => User::ROLE_SYSOP]);

    $this->actingAs($sysop)
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_ADMIN,
            'audit_reason' => 'Sysop access no longer needed.',
        ])
        ->assertRedirect();

    expect($target->fresh()->role)->toBe(User::ROLE_ADMIN);
});

it('prevents users from changing their own role', function (string $role): void {
    $actor = User::factory()->create(['role' => $role]);

    $this->actingAs($actor)
        ->patch(route('admin.users.role.update', $actor), [
            'role' => User::ROLE_USER,
            'audit_reason' => 'Self service change.',
        ])
        ->assertForbidden();

    expect($actor->fresh()->role)->toBe($role);
})->with([
    User::ROLE_USER,
    User::ROLE_ADMIN,
    User::ROLE_SYSOP,
]);

it('requires an audit reason for role updates', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($admin)
        ->from('/admin/users')
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_MODERATOR,
            'audit_reason' => '   ',
        ])
        ->assertRedirect('/admin/users')
        ->assertSessionHasErrors('audit_reason');

    expect($target->fresh()->role)->toBe(User::ROLE_USER);
});

it('preserves normal admin role management and logs the change', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $target = User::factory()->create(['role' => User::ROLE_USER]);

    $this->actingAs($admin)
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_MODERATOR,
            'audit_reason' => 'Promoted to help with moderation.',
        ])
        ->assertRedirect();

    expect($target->fresh()->role)->toBe(User::ROLE_MODERATOR);

    $log = AuditLog::query()->where('action', 'user.role_changed')->firstOrFail();

    expect($log->user_id)->toBe($admin->id)
        ->and($log->target_id)->toBe($target->id)
        ->and($log->metadata)->toMatchArray([
            'actor_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'old_role' => User::ROLE_USER,
            'new_role' => User::ROLE_MODERATOR,
            'audit_reason' => 'Promoted to help with moderation.',
        ]);
});

it('revokes inherited legacy staff powers when a member is downgraded to user', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $legacyAdmin = Role::query()->where('slug', 'admin1')->firstOrFail();
    $legacyUser = Role::query()->where('slug', 'user1')->firstOrFail();
    $target = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'role_id' => $legacyAdmin->getKey(),
        'is_staff' => true,
    ]);

    expect($target->isStaff())->toBeTrue()
        ->and($target->roleLevel())->toBeGreaterThanOrEqual(10);

    $this->actingAs($sysop)
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_USER,
            'audit_reason' => 'Demote after staff review.',
        ])
        ->assertRedirect();

    $demoted = $target->fresh();
    expect($demoted->role)->toBe(User::ROLE_USER)
        ->and($demoted->role_id)->toBe($legacyUser->getKey())
        ->and($demoted->is_staff)->toBeFalse()
        ->and($demoted->roleLevel())->toBe(1)
        ->and($demoted->isStaff())->toBeFalse()
        ->and($demoted->isAdmin())->toBeFalse()
        ->and($demoted->isSysop())->toBeFalse()
        ->and(PermissionService::allow($demoted, 'torrent.edit'))->toBeFalse();

    $this->actingAs($demoted)->get('/mod')->assertForbidden();
    $this->get('/admin')->assertForbidden();
});

it('keeps staff flag and role relation aligned when changing staff access', function (): void {
    $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
    $target = User::factory()->create(['role' => User::ROLE_USER]);
    $legacyModerator = Role::query()->where('slug', 'mod1')->firstOrFail();
    $legacyUploader = Role::query()->where('slug', 'uploader1')->firstOrFail();

    $this->actingAs($sysop)
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_MODERATOR,
            'audit_reason' => 'Grant moderation access.',
        ])
        ->assertRedirect();

    $moderator = $target->fresh();
    expect($moderator->role_id)->toBe($legacyModerator->getKey())
        ->and($moderator->is_staff)->toBeTrue()
        ->and($moderator->isStaff())->toBeTrue();

    $this->patch(route('admin.users.role.update', $moderator), [
        'role' => User::ROLE_UPLOADER,
        'audit_reason' => 'Return to uploader responsibilities.',
    ])->assertRedirect();

    $uploader = $target->fresh();
    expect($uploader->role_id)->toBe($legacyUploader->getKey())
        ->and($uploader->is_staff)->toBeFalse()
        ->and($uploader->isStaff())->toBeFalse()
        ->and($uploader->roleLevel())->toBe(5);
});

it('protects sysop accounts even when the older role relation disagrees with the role column', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $legacySysop = Role::query()->where('slug', 'sysop')->firstOrFail();
    $target = User::factory()->create([
        'role' => User::ROLE_USER,
        'role_id' => $legacySysop->getKey(),
        'is_staff' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.role.update', $target), [
            'role' => User::ROLE_USER,
            'audit_reason' => 'Attempt to remove protected access.',
        ])
        ->assertForbidden();

    expect($target->fresh()->role_id)->toBe($legacySysop->getKey());
});
