<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('promotes a user to the target role', function (): void {
    $initialRoleId = Role::query()->where('slug', 'newbie')->value('id');
    $targetRole = Role::query()->where('slug', 'admin1')->firstOrFail();

    $user = User::query()->create([
        'name' => 'Promo Tester',
        'email' => 'promo@example.com',
        'password' => 'password',
        'role_id' => $initialRoleId,
        'email_verified_at' => now(),
    ]);

    artisan('user:promote', [
        'email' => $user->email,
        'roleSlug' => $targetRole->slug,
    ])->expectsOutput("User '{$user->email}' promoted to role '{$targetRole->name}' ({$targetRole->slug}).")
        ->assertSuccessful();

    expect($user->fresh()->role_id)->toBe($targetRole->getKey());
});

it('fails when the target role is missing', function (): void {
    $initialRoleId = Role::query()->where('slug', 'user1')->value('id');

    $user = User::query()->create([
        'name' => 'Missing Role Tester',
        'email' => 'missing-role@example.com',
        'password' => 'password',
        'role_id' => $initialRoleId,
        'email_verified_at' => now(),
    ]);

    artisan('user:promote', [
        'email' => $user->email,
        'roleSlug' => 'non-existent-role',
    ])->expectsOutput("Role with slug 'non-existent-role' was not found.")
        ->assertExitCode(SymfonyCommand::FAILURE);

    expect($user->fresh()->role_id)->toBe($initialRoleId);
});
