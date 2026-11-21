<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnatchlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_snatchlist_displays_completed_torrents(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['name' => 'Example Torrent']);

        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 1_342_177_280, // 1.25 GiB
            'downloaded' => 5_905_580_032, // 5.50 GiB
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/account/snatches');

        $response->assertOk();
        $response->assertSee('Example Torrent');
        $response->assertSee('1.25 GB');
        $response->assertSee('5.50 GB');
        $response->assertDontSee('1,342,177,280 B');
        $response->assertDontSee('5,905,580,032 B');
    }

    public function test_snatchlist_shows_empty_state_when_no_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/account/snatches');

        $response->assertOk();
        $response->assertSee('No completed torrents yet');
    }
}
