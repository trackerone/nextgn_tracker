<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TorrentDownloadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_rejected_from_download(): void
    {
        $torrent = Torrent::factory()->create();

        $this->getJson('/api/torrents/'.$torrent->id.'/download')->assertStatus(401);
    }

    public function test_authenticated_user_can_download_torrent(): void
    {
        Storage::fake('torrents');
        config()->set('tracker.announce_url', 'https://tracker.example/announce/%s');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['slug' => 'download-me']);

        $payload = app(BencodeService::class)->encode([
            'announce' => 'https://old.invalid/announce',
            'info' => ['name' => 'demo-file'],
        ]);

        Storage::disk('torrents')->put($torrent->torrentStoragePath(), $payload);

        $response = $this->actingAs($user)->get('/api/torrents/'.$torrent->id.'/download');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-bittorrent');

        $decoded = app(BencodeService::class)->decode($response->streamedContent());

        $this->assertIsArray($decoded);
        $this->assertSame(sprintf('https://tracker.example/announce/%s', $user->passkey), $decoded['announce'] ?? null);
    }

    public function test_pending_torrent_returns_404_for_regular_user(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->unapproved()->create();

        $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id.'/download')->assertNotFound();
    }

    public function test_rejected_torrent_returns_404_for_regular_user(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->rejected()->create();

        $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id.'/download')->assertNotFound();
    }
}
