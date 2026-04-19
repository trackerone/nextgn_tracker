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

final class PersonalizedDiscoveryFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_shows_relevant_matches_and_hides_irrelevant_items(): void
    {
        $user = User::factory()->create();

        TorrentFollow::factory()->create([
            'user_id' => $user->id,
            'title' => 'Signal',
            'normalized_title' => 'signal',
            'type' => 'tv',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);

        $relevant = Torrent::factory()->create(['name' => 'Signal S01E01 1080p WEB-DL']);
        $irrelevant = Torrent::factory()->create(['name' => 'Different Show S01E01 2160p WEB-DL']);

        TorrentMetadata::query()->insert([
            [
                'torrent_id' => $relevant->id,
                'title' => 'Signal',
                'type' => 'tv',
                'resolution' => '1080p',
                'source' => 'WEB-DL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'torrent_id' => $irrelevant->id,
                'title' => 'Different Show',
                'type' => 'tv',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('my.discovery'));

        $response->assertOk();
        $response->assertSee($relevant->name);
        $response->assertDontSee($irrelevant->name);
    }

    public function test_feed_ordering_is_unseen_then_featured_then_recency(): void
    {
        $user = User::factory()->create();

        TorrentFollow::factory()->create([
            'user_id' => $user->id,
            'title' => 'Signal',
            'normalized_title' => 'signal',
            'last_checked_at' => Carbon::parse('2026-04-10 10:00:00'),
        ]);

        $unseen = Torrent::factory()->create([
            'name' => 'Signal 2026 S01E03 720p WEBRIP',
            'created_at' => Carbon::parse('2026-04-10 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-10 11:00:00'),
        ]);
        $featuredSeen = Torrent::factory()->create([
            'name' => 'Signal 2026 S01E01 1080p WEB-DL',
            'created_at' => Carbon::parse('2026-04-10 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-10 09:00:00'),
        ]);
        $recentSeen = Torrent::factory()->create([
            'name' => 'Signal 2026 S01E02 SDTV',
            'created_at' => Carbon::parse('2026-04-10 12:00:00'),
            'updated_at' => Carbon::parse('2026-04-10 12:00:00'),
        ]);

        TorrentMetadata::query()->insert([
            [
                'torrent_id' => $unseen->id,
                'title' => 'Signal 2026 S01E03',
                'type' => 'tv',
                'resolution' => '720p',
                'source' => 'WEBRIP',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'torrent_id' => $featuredSeen->id,
                'title' => 'Signal 2026 S01E01',
                'type' => 'tv',
                'resolution' => '1080p',
                'source' => 'WEB-DL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'torrent_id' => $recentSeen->id,
                'title' => 'Signal 2026 S01E02',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('my.discovery'));

        $response->assertOk();
        $response->assertSeeInOrder([
            $unseen->name,
            $featuredSeen->name,
            $recentSeen->name,
        ]);
    }

    public function test_feed_renders_empty_state_when_no_follows_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('my.discovery'));

        $response->assertOk();
        $response->assertSee('You have no follows yet');
        $response->assertSee('Create your first follow');
    }

    public function test_feed_renders_empty_state_when_no_matches_exist(): void
    {
        $user = User::factory()->create();

        TorrentFollow::factory()->create([
            'user_id' => $user->id,
            'title' => 'No Match Title',
            'normalized_title' => 'no match title',
        ]);

        Torrent::factory()->create(['name' => 'Completely Different']);

        $response = $this->actingAs($user)->get(route('my.discovery'));

        $response->assertOk();
        $response->assertSee('No relevant releases found');
        $response->assertSee('Update follow preferences');
    }
}
