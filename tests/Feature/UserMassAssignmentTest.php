<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_privileged_user_attributes_are_not_mass_assignable(): void
    {
        $staffRole = Role::query()->firstOrCreate(
            ['slug' => 'mod1'],
            [
                'name' => 'Staff',
                'level' => 8,
                'is_staff' => true,
            ],
        );

        $filledUser = new User;
        $filledUser->fill([
            'name' => 'Filled User',
            'email' => 'filled@example.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $staffRole->getKey(),
            'passkey' => 'filled-passkey',
            'is_banned' => true,
            'is_disabled' => true,
            'is_staff' => true,
        ]);

        $this->assertSame('Filled User', $filledUser->name);
        $this->assertNull($filledUser->getAttribute('role'));
        $this->assertNull($filledUser->role_id);
        $this->assertNull($filledUser->passkey);
        $this->assertFalse((bool) $filledUser->is_banned);
        $this->assertFalse((bool) $filledUser->is_disabled);
        $this->assertFalse((bool) $filledUser->is_staff);

        $user = User::create([
            'name' => 'Mass Assignment Probe',
            'email' => 'mass-assignment@example.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'role_id' => $staffRole->getKey(),
            'passkey' => 'attacker-controlled-passkey',
            'is_banned' => true,
            'is_disabled' => true,
            'is_staff' => true,
        ])->refresh();

        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertNull($user->role_id);
        $this->assertNull($user->passkey);
        $this->assertFalse($user->is_banned);
        $this->assertFalse($user->is_disabled);
        $this->assertFalse($user->is_staff);
    }

    public function test_normal_user_creation_still_allows_safe_profile_attributes(): void
    {
        $user = User::create([
            'name' => 'Safe User',
            'email' => 'safe-user@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->refresh();

        $this->assertSame('Safe User', $user->name);
        $this->assertSame('safe-user@example.test', $user->email);
        $this->assertTrue(Hash::check('correct-horse-battery-staple', $user->password));
        $this->assertSame(User::ROLE_USER, $user->role);
    }

    public function test_staff_factory_state_can_still_create_privileged_users(): void
    {
        $user = User::factory()->staff()->create()->refresh();

        $this->assertTrue($user->isStaff());
        $this->assertTrue((bool) $user->is_staff);
    }

    public function test_admin_role_update_flow_still_uses_explicit_assignment(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $target = User::factory()->create([
            'role' => User::ROLE_USER,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.role.update', $target), [
                'role' => User::ROLE_MODERATOR,
            ])
            ->assertRedirect();

        $this->assertSame(User::ROLE_MODERATOR, $target->fresh()->role);
    }
}
