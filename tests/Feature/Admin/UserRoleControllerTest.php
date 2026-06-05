<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
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
