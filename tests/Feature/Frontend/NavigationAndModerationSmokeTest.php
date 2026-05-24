<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NavigationAndModerationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_navigation_links_forum_and_pm_to_canonical_routes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('href="'.route('topics.index').'"', false);
        $response->assertSee('href="'.route('pm.index').'"', false);
    }

    public function test_staff_moderation_page_renders_and_non_staff_is_forbidden(): void
    {
        $this->seed(RoleSeeder::class);

        $memberRole = Role::query()->where('slug', 'user1')->firstOrFail();
        $member = User::factory()->create([
            'role' => $memberRole->slug,
            'role_id' => $memberRole->getKey(),
            'is_staff' => false,
        ]);

        $staffRole = Role::query()->where('slug', 'moderator')->firstOrFail();
        $staff = User::factory()->create([
            'role' => $staffRole->slug,
            'role_id' => $staffRole->getKey(),
            'is_staff' => true,
        ]);

        $this->actingAs($member)
            ->get(route('staff.torrents.moderation.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('staff.torrents.moderation.index'))
            ->assertOk()
            ->assertSee('Pending torrent review queue');
    }
}
