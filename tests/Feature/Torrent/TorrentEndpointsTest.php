<?php

declare(strict_types=1);

namespace Tests\Feature\Torrent;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorrentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_torrents(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $response = $this->actingAs($user)->getJson('/torrents');

        $response
            ->assertOk()
            ->assertJsonFragment(['slug' => $torrent->slug]);
    }

    public function test_authenticated_user_can_view_single_torrent(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $response = $this->actingAs($user)->getJson("/torrents/{$torrent->slug}");

        $response
            ->assertOk()
            ->assertJsonFragment(['slug' => $torrent->slug]);
    }

    public function test_show_returns_not_found_for_missing_torrent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/torrents/missing-slug');

        $response->assertNotFound();
    }
}
