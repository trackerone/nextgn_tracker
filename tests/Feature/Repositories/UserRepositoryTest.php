<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Contracts\UserRepositoryInterface;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_promote_to_role_assigns_target_role(): void
    {
        $repository = $this->app->make(UserRepositoryInterface::class);
        $userRole = Role::query()->where('slug', 'user1')->firstOrFail();
        $targetRole = Role::query()->where('slug', 'mod1')->firstOrFail();
        $user = User::factory()->create(['role_id' => $userRole->getKey()]);

        $repository->promoteToRole($user, 'mod1');

        $this->assertTrue($user->fresh()->role->is($targetRole));
    }

    public function test_promote_to_role_is_noop_when_role_missing(): void
    {
        $repository = $this->app->make(UserRepositoryInterface::class);
        $userRole = Role::query()->where('slug', 'user2')->firstOrFail();
        $user = User::factory()->create(['role_id' => $userRole->getKey()]);

        $repository->promoteToRole($user, 'missing-role');

        $this->assertSame($userRole->getKey(), $user->fresh()->role_id);
    }

    public function test_all_staff_returns_only_staff_sorted_by_name(): void
    {
        $repository = $this->app->make(UserRepositoryInterface::class);
        $modRole = Role::query()->where('slug', 'mod1')->firstOrFail();
        $adminRole = Role::query()->where('slug', 'admin1')->firstOrFail();
        $memberRole = Role::query()->where('slug', 'user1')->firstOrFail();

        $staffAlpha = User::factory()->create([
            'name' => 'Aaron Staff',
            'role_id' => $modRole->getKey(),
        ]);

        $staffBeta = User::factory()->create([
            'name' => 'Zara Staff',
            'role_id' => $adminRole->getKey(),
        ]);

        User::factory()->create([
            'name' => 'Casual Member',
            'role_id' => $memberRole->getKey(),
        ]);

        $staff = $repository->allStaff();

        $this->assertSame([
            $staffAlpha->getKey(),
            $staffBeta->getKey(),
        ], $staff->pluck('id')->all());
    }
}
