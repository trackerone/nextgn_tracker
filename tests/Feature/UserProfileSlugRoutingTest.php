<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserProfileSlugRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeric_user_profile_urls_do_not_reveal_users_by_database_id(): void
    {
        $user = User::factory()->create([
            'name' => 'trackerone',
            'public_slug' => 'trackerone',
        ]);

        $this->get('/users/'.$user->id)
            ->assertNotFound()
            ->assertDontSee($user->name);
    }

    public function test_user_profile_urls_resolve_by_public_slug(): void
    {
        $user = User::factory()->create([
            'name' => 'trackerone',
            'public_slug' => 'trackerone',
        ]);

        $this->get('/users/trackerone')
            ->assertOk()
            ->assertSee('trackerone')
            ->assertDontSee('/users/'.$user->id, false)
            ->assertDontSee($user->email)
            ->assertDontSee($user->passkey);
    }

    public function test_generated_torrent_profile_links_use_public_slug_not_database_id(): void
    {
        $viewer = User::factory()->create();
        $uploader = User::factory()->create([
            'name' => 'trackerone',
            'public_slug' => 'trackerone',
        ]);
        $torrent = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'slug' => 'slug-route-contract',
        ]);

        $this->actingAs($viewer)
            ->get(route('torrents.show', $torrent))
            ->assertOk()
            ->assertSee('/users/trackerone', false)
            ->assertDontSee('/users/'.$uploader->id, false);
    }
}
