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

    public function test_download_returns_personalized_file_when_exists(): void
    {
        Storage::fake('torrents');
        config()->set('tracker.announce_url', 'https://tracker.example/announce/%s');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->storeTorrentPayload($torrent, [
            'announce' => 'https://tracker.invalid/announce',
            'info' => ['name' => 'demo'],
        ]);

        $response = $this->actingAs($user)->get('/torrents/'.$torrent->getKey().'/download');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-bittorrent');

        $decoded = $this->decodeTorrentPayload($response->streamedContent());

        $this->assertSame(sprintf('https://tracker.example/announce/%s', $user->passkey), $decoded['announce'] ?? null);
    }

    public function test_download_strips_uploaded_announce_list(): void
    {
        Storage::fake('torrents');
        config()->set('tracker.announce_url', 'https://tracker.example/announce/%s');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->storeTorrentPayload($torrent, [
            'announce' => 'https://tracker.invalid/announce',
            'announce-list' => [
                ['https://leaked-one.invalid/announce'],
                ['https://leaked-two.invalid/announce'],
            ],
            'info' => ['name' => 'demo'],
        ]);

        $response = $this->actingAs($user)->get('/torrents/'.$torrent->getKey().'/download');

        $response->assertOk();

        $decoded = $this->decodeTorrentPayload($response->streamedContent());

        $this->assertArrayNotHasKey('announce-list', $decoded);
        $this->assertSame(sprintf('https://tracker.example/announce/%s', $user->passkey), $decoded['announce'] ?? null);
    }

    public function test_web_and_api_download_payloads_match_for_same_user_and_torrent(): void
    {
        Storage::fake('torrents');
        config()->set('tracker.announce_url', 'https://tracker.example/announce/%s');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['slug' => 'shared-download']);

        $this->storeTorrentPayload($torrent, [
            'announce' => 'https://tracker.invalid/announce',
            'announce-list' => [
                ['https://leaked.invalid/announce'],
            ],
            'info' => ['name' => 'demo'],
        ]);

        $webResponse = $this->actingAs($user)->get('/torrents/'.$torrent->getKey().'/download');
        $apiResponse = $this->actingAs($user)->get('/api/torrents/'.$torrent->getKey().'/download');

        $webResponse->assertOk();
        $apiResponse->assertOk();

        $webPayload = $webResponse->streamedContent();
        $apiPayload = $apiResponse->streamedContent();

        $this->assertSame($apiPayload, $webPayload);
        $this->assertSame(
            $this->decodeTorrentPayload($apiPayload),
            $this->decodeTorrentPayload($webPayload)
        );
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeTorrentPayload(Torrent $torrent, array $payload): void
    {
        Storage::disk('torrents')->put(
            $torrent->torrentStoragePath(),
            app(BencodeService::class)->encode($payload)
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeTorrentPayload(string $payload): array
    {
        $decoded = app(BencodeService::class)->decode($payload);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
