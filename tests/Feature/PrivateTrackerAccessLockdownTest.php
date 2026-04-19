<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateTrackerAccessLockdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_internal_web_surfaces(): void
    {
        $torrent = Torrent::factory()->create();
        $topic = Topic::factory()->create();

        $this->get(route('torrents.index'))->assertRedirect(route('login'));
        $this->get(route('torrents.show', $torrent))->assertRedirect(route('login'));
        $this->get(route('torrents.upload'))->assertRedirect(route('login'));
        $this->get(route('my.follows'))->assertRedirect(route('login'));
        $this->get(route('topics.index'))->assertRedirect(route('login'));
        $this->get(route('topics.show', $topic))->assertRedirect(route('login'));
        $this->get(route('staff.torrents.moderation.index'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_internal_api_surfaces(): void
    {
        $torrent = Torrent::factory()->create();

        $this->getJson(route('api.torrents.index'))->assertUnauthorized();
        $this->getJson(route('api.torrents.show', $torrent))->assertUnauthorized();
        $this->getJson(route('api.torrents.download', $torrent))->assertUnauthorized();
        $this->postJson(route('api.uploads.store'))->assertUnauthorized();
        $this->getJson(route('api.my.uploads'))->assertUnauthorized();
        $this->getJson(route('api.moderation.uploads.index'))->assertUnauthorized();
        $this->postJson(route('api.moderation.uploads.approve', $torrent))->assertUnauthorized();
    }

    public function test_authenticated_user_retains_access_to_intended_internal_pages(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->actingAs($user)->get(route('torrents.index'))->assertOk();
        $this->actingAs($user)->get(route('torrents.show', $torrent))->assertOk();
        $this->actingAs($user)->get(route('my.follows'))->assertOk();
    }

    public function test_staff_pages_and_api_still_require_staff_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $memberRole = Role::query()->where('slug', 'user1')->firstOrFail();
        $member = User::factory()->create([
            'role' => $memberRole->slug,
            'role_id' => $memberRole->getKey(),
            'is_staff' => false,
        ]);

        $torrent = Torrent::factory()->unapproved()->create();

        $this->actingAs($member)
            ->get(route('staff.torrents.moderation.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson(route('api.moderation.uploads.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->postJson(route('api.moderation.uploads.approve', $torrent))
            ->assertForbidden();
    }

    public function test_public_pages_render_for_guests_without_layout_auth_assumptions(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('health.index'))->assertOk();
    }
}
