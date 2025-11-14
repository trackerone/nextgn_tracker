<?php

declare(strict_types=1);

namespace Tests\Feature\Forum;

use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TopicAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_regular_user_cannot_lock_topic(): void
    {
        $user = $this->userWithRole('user1');
        $topic = Topic::factory()->create();

        $this->actingAs($user);

        $this->postJson("/topics/{$topic->getKey()}/lock")
            ->assertStatus(403);
    }

    public function test_moderator_can_lock_topic(): void
    {
        $moderator = $this->userWithRole('mod1');
        $topic = Topic::factory()->create();

        $this->actingAs($moderator);

        $this->postJson("/topics/{$topic->getKey()}/lock")
            ->assertOk()
            ->assertJson(['is_locked' => true]);
    }

    public function test_topic_owner_cannot_delete_without_admin_role(): void
    {
        $owner = $this->userWithRole('user1');
        $topic = Topic::factory()->for($owner, 'author')->create();

        $this->actingAs($owner);

        $this->deleteJson("/topics/{$topic->getKey()}")
            ->assertStatus(403);
    }

    public function test_admin_can_delete_topic(): void
    {
        $admin = $this->userWithRole('admin1');
        $topic = Topic::factory()->create();

        $this->actingAs($admin);

        $this->deleteJson("/topics/{$topic->getKey()}")
            ->assertNoContent();
    }

    private function userWithRole(string $slug): User
    {
        $roleId = Role::query()->where('slug', $slug)->value('id');

        if ($roleId === null) {
            throw new InvalidArgumentException("Role '{$slug}' does not exist.");
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }
}
