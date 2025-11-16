<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_see_details(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Detail Test',
            'type' => 'movie',
            'seeders' => 10,
            'leechers' => 2,
            'completed' => 5,
            'imdb_id' => 'tt1234567',
            'tmdb_id' => '7654321',
            'description' => "Line one\n<script>alert('x')</script>",
            'nfo_text' => 'Example nfo content',
        ]);

        $response = $this->actingAs($user)->get('/torrents/'.$torrent->getKey());

        $response->assertOk();
        $response->assertSee('Detail Test');
        $response->assertSee('movie');
        $response->assertSee((string) $torrent->formatted_size);
        $response->assertSee((string) $torrent->seeders);
        $response->assertSee((string) $torrent->leechers);
        $response->assertSee((string) $torrent->completed);
        $response->assertSee('tt1234567');
        $response->assertSee('7654321');
        $response->assertSee('&lt;script&gt;alert', false);
        $response->assertSee('Example nfo content');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
