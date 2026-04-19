<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentFollow;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

        $follow = TorrentFollow::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($follow->last_checked_at);
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

        $follow = TorrentFollow::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($follow->last_checked_at);
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
            'last_checked_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($user)->get('/my/follows');

        $response->assertOk();
        $response->assertSee($matchingTorrent->name);
        $response->assertDontSee($nonMatchingTorrent->name);
    }

    public function test_my_follows_matches_legacy_torrent_by_title_when_metadata_is_missing(): void
    {
        $user = User::factory()->create();
        $matchingTorrent = Torrent::factory()->create(['name' => 'Legacy One 2026 Proper']);
        $nonMatchingTorrent = Torrent::factory()->create(['name' => 'Completely Different']);

        TorrentFollow::factory()->create([
            'user_id' => $user->id,
            'title' => 'Legacy One',
            'normalized_title' => 'legacy one',
            'type' => null,
            'resolution' => null,
            'source' => null,
            'year' => null,
        ]);

        $response = $this->actingAs($user)->get('/my/follows');

        $response->assertOk();
        $response->assertSee($matchingTorrent->name);
        $response->assertDontSee($nonMatchingTorrent->name);
    }

    public function test_my_follows_surfaces_new_match_count_and_marks_seen_on_visit(): void
    {
        $user = User::factory()->create();
        $follow = TorrentFollow::factory()->create([
            'user_id' => $user->id,
            'title' => 'Signal',
            'normalized_title' => 'signal',
            'last_checked_at' => Carbon::parse('2026-04-10 10:00:00'),
        ]);

        $oldMatch = Torrent::factory()->create([
            'name' => 'Signal 2026 S01E01',
            'created_at' => Carbon::parse('2026-04-10 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-10 09:00:00'),
        ]);

        $newMatch = Torrent::factory()->create([
            'name' => 'Signal 2026 S01E02',
            'created_at' => Carbon::parse('2026-04-10 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-10 11:00:00'),
        ]);

        TorrentMetadata::query()->insert([
            [
                'torrent_id' => $oldMatch->id,
                'title' => 'Signal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'torrent_id' => $newMatch->id,
                'title' => 'Signal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->travelTo(Carbon::parse('2026-04-10 12:00:00'));

        $response = $this->actingAs($user)->get('/my/follows');

        $response->assertOk();
        $response->assertSee('1 new matches');
        $response->assertSee('New / unseen (1)');
        $response->assertSee('All matches (2)');
        $response->assertSee($newMatch->name);

        $follow->refresh();
        $this->assertEquals('2026-04-10 12:00:00', $follow->last_checked_at?->format('Y-m-d H:i:s'));

        $secondResponse = $this->actingAs($user)->get('/my/follows');
        $secondResponse->assertSee('0 new matches');
        $secondResponse->assertSee('New / unseen (0)');

        $this->travelBack();
    }

    public function test_first_visit_after_legacy_follow_without_check_timestamp_treats_matches_as_new(): void
    {
        $user = User::factory()->create();
        $follow = TorrentFollow::factory()->create([
            'user_id' => $user->id,
            'title' => 'Archive Show',
            'normalized_title' => 'archive show',
            'last_checked_at' => null,
        ]);

        $match = Torrent::factory()->create(['name' => 'Archive Show 2026']);
        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'title' => 'Archive Show',
        ]);

        $response = $this->actingAs($user)->get('/my/follows');

        $response->assertOk();
        $response->assertSee('1 new matches');
        $response->assertSee('New / unseen (1)');

        $follow->refresh();
        $this->assertNotNull($follow->last_checked_at);
    }
}
