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

    public function test_primary_navigation_uses_livable_alpha_labels(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Browse');
        $response->assertSee('Messages');
        $response->assertSeeText('Ratio & snatches');
        $response->assertDontSeeText('Staff moderation');
    }

    public function test_dashboard_shows_conservative_core_tracker_orientation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSeeText('Core tracker path');
        $response->assertSeeText('ordinary tracker path');
        $response->assertSeeText('Browse torrents');
        $response->assertSeeText('Upload release');
        $response->assertSeeText('My uploads');
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

        $staffRole = Role::query()->whereIn('slug', ['mod1', 'mod2', 'admin1', 'admin2', 'sysop'])->firstOrFail();
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
            ->assertSeeText('Pending torrent review queue')
            ->assertSeeText('Review guidance')
            ->assertSeeText('Approve')
            ->assertSeeText('Reject with an actionable reason')
            ->assertSeeText('Soft-delete only when appropriate')
            ->assertSeeText('Launch readiness checks')
            ->assertSeeText('No pending uploads right now')
            ->assertSeeText('Staff moderation');
    }
}
