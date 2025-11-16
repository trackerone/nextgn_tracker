<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TorrentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_download_or_magnet(): void
    {
        $torrent = Torrent::factory()->create();

        $this->get('/torrents/'.$torrent->getKey().'/download')->assertRedirect('/login');
        $this->get('/torrents/'.$torrent->getKey().'/magnet')->assertRedirect('/login');
    }

    public function test_download_returns_file_when_exists(): void
    {
        Storage::fake('torrents');
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $payload = app(BencodeService::class)->encode([
            'announce' => 'https://tracker.invalid/announce',
            'info' => ['name' => 'demo'],
        ]);

        Storage::disk('torrents')->put($torrent->torrentStoragePath(), $payload);

        $response = $this->actingAs($user)->get('/torrents/'.$torrent->getKey().'/download');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-bittorrent');
    }

    public function test_download_returns404_when_file_missing(): void
    {
        Storage::fake('torrents');
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->actingAs($user)
            ->get('/torrents/'.$torrent->getKey().'/download')
            ->assertNotFound();
    }

    public function test_magnet_returns_tracker_information(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'info_hash' => 'ABCDEF1234ABCDEF1234ABCDEF1234ABCDEF1234',
            'name' => 'My <script>bad</script> Torrent',
        ]);

        config()->set('tracker.announce_url', 'https://nextgn.example/announce');
        config()->set('tracker.additional_trackers', ['https://backup.example/announce']);

        $response = $this->actingAs($user)->getJson('/torrents/'.$torrent->getKey().'/magnet');

        $response->assertOk();
        $magnet = $response->json('magnet');

        $this->assertIsString($magnet);
        $this->assertStringContainsString('xt=urn:btih:ABCDEF1234ABCDEF1234ABCDEF1234ABCDEF1234', $magnet);
        $this->assertStringContainsString(rawurlencode('https://nextgn.example/announce'), $magnet);
        $this->assertStringContainsString(rawurlencode('https://backup.example/announce'), $magnet);
        $this->assertStringNotContainsString('<script>', $magnet);
    }
}
