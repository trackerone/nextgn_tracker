<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_and_create_invites(): void
    {
        $staffRole = Role::query()->create([
            'slug' => 'staff',
            'name' => 'Staff',
            'level' => 10,
            'is_staff' => true,
        ]);

        $staff = User::factory()->create(['role_id' => $staffRole->id]);

        $this->actingAs($staff);

        $this->get('/admin/invites')->assertOk();

        $response = $this->post('/admin/invites', [
            'max_uses' => 2,
            'expires_at' => now()->addDay()->toDateTimeString(),
            'notes' => 'Testing invite',
        ]);

        $response->assertRedirect(route('admin.invites.index'));

        $this->assertDatabaseHas('invites', [
            'max_uses' => 2,
            'notes' => 'Testing invite',
            'inviter_user_id' => $staff->id,
        ]);
    }

    public function test_regular_users_cannot_access_admin_invites(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/admin/invites')->assertForbidden();
    }
}
