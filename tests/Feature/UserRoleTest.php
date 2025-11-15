<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_helpers_detect_staff_levels(): void
    {
        $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        $this->assertTrue($moderator->isStaff());
        $this->assertTrue($moderator->isModerator());
        $this->assertFalse($moderator->isAdmin());

        $this->assertTrue($admin->isStaff());
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSysop());

        $this->assertFalse($member->isStaff());
        $this->assertSame('User', $member->userClass());
    }

    public function test_staff_scope_filters_users(): void
    {
        User::factory()->create(['role' => User::ROLE_MODERATOR]);
        User::factory()->create(['role' => User::ROLE_USER]);

        $this->assertCount(1, User::staff()->get());
    }
}
