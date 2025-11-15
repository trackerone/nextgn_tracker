<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestsCannotAccessIndex(): void
    {
        $this->get('/torrents')->assertRedirect('/login');
    }

    public function testGuestsCannotAccessShow(): void
    {
        $torrent = Torrent::factory()->create();

        $this->get('/torrents/'.$torrent->getKey())->assertRedirect('/login');
    }

    public function testAuthenticatedUserSeesPaginatedList(): void
    {
        $user = User::factory()->create();
        Torrent::factory()->count(30)->create();

        $response = $this->actingAs($user)->get('/torrents');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertSee(Torrent::query()->latest('id')->first()?->name ?? '');
    }

    public function testSearchFilterLimitsResults(): void
    {
        $user = User::factory()->create();
        $match = Torrent::factory()->create(['name' => 'Alpha Release', 'tags' => ['alpha']]);
        $miss = Torrent::factory()->create(['name' => 'Beta Release', 'tags' => ['beta']]);

        $response = $this->actingAs($user)->get('/torrents?q=Alpha');

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($miss->name);
    }

    public function testTypeFilterWorks(): void
    {
        $user = User::factory()->create();
        $movie = Torrent::factory()->create(['type' => 'movie']);
        $music = Torrent::factory()->create(['type' => 'music']);

        $response = $this->actingAs($user)->get('/torrents?type=music');

        $response->assertOk();
        $response->assertSee($music->name);
        $response->assertDontSee($movie->name);
    }

    public function testOrderAndDirectionAffectSorting(): void
    {
        $user = User::factory()->create();
        $low = Torrent::factory()->create(['seeders' => 1, 'uploaded_at' => now()->subDay()]);
        $high = Torrent::factory()->create(['seeders' => 200, 'uploaded_at' => now()]);

        $response = $this->actingAs($user)->get('/torrents?order=seeders&direction=asc');
        $response->assertOk();
        $response->assertSeeInOrder([$low->name, $high->name]);

        $responseDesc = $this->actingAs($user)->get('/torrents?order=seeders&direction=desc');
        $responseDesc->assertOk();
        $responseDesc->assertSeeInOrder([$high->name, $low->name]);
    }

    public function testPendingTorrentsAreHiddenFromIndex(): void
    {
        $user = User::factory()->create();
        $approved = Torrent::factory()->create();
        $pending = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING]);

        $response = $this->actingAs($user)->get('/torrents');

        $response->assertOk();
        $response->assertSee($approved->name);
        $response->assertDontSee($pending->name);
    }
}
