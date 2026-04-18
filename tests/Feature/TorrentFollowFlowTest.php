<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentFollow;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentFollowFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_follow_from_manual_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/my/follows', [
            'title' => 'Planet Earth',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'year' => 2023,
        ]);

        $response->assertRedirect('/my/follows');

        $this->assertDatabaseHas('torrent_follows', [
            'user_id' => $user->id,
            'title' => 'Planet Earth',
            'normalized_title' => 'planet earth',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'year' => 2023,
        ]);
    }

    public function test_user_can_create_follow_from_torrent_detail_page(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['slug' => 'planet-release']);
        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Planet Release',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'BLURAY',
            'year' => 2025,
        ]);

        $this->actingAs($user)
            ->post(route('torrents.follow.store', $torrent))
            ->assertRedirect('/my/follows');

        $this->assertDatabaseHas('torrent_follows', [
            'user_id' => $user->id,
            'title' => 'Planet Release',
            'normalized_title' => 'planet release',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'BLURAY',
            'year' => 2025,
        ]);
    }

    public function test_my_follows_displays_only_matching_torrents(): void
    {
        $user = User::factory()->create();
        $matchingTorrent = Torrent::factory()->create(['name' => 'Legacy One']);
        $nonMatchingTorrent = Torrent::factory()->create(['name' => 'Legacy Two']);

        TorrentMetadata::query()->create([
            'torrent_id' => $matchingTorrent->id,
            'title' => 'Future World',
            'resolution' => '1080p',
            'type' => 'movie',
            'source' => 'WEB-DL',
            'year' => 2024,
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $nonMatchingTorrent->id,
            'title' => 'Future World',
            'resolution' => '720p',
            'type' => 'movie',
            'source' => 'WEB-DL',
            'year' => 2024,
        ]);

        TorrentFollow::factory()->create([
            'user_id' => $user->id,
            'title' => 'Future World',
            'normalized_title' => 'future world',
            'resolution' => '1080p',
        ]);

        $response = $this->actingAs($user)->get('/my/follows');

        $response->assertOk();
        $response->assertSee($matchingTorrent->name);
        $response->assertDontSee($nonMatchingTorrent->name);
    }
}

