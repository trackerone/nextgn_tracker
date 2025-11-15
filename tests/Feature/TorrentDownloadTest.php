<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TorrentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_download_torrent(): void
    {
        Storage::fake('torrents');
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        Storage::disk('torrents')->put($torrent->torrentStoragePath(), $this->makeTorrentPayload());

        $response = $this->actingAs($user)->get(route('torrents.download', $torrent));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/x-bittorrent');
        $expectedFilename = Str::slug($torrent->name).'.torrent';
        $response->assertHeader('content-disposition', 'attachment; filename="'.$expectedFilename.'"');

        $decoded = app(BencodeService::class)->decode((string) $response->getContent());
        $this->assertIsArray($decoded);
        $this->assertSame($user->announce_url, $decoded['announce']);
        $userTorrent = UserTorrent::query()
            ->where('user_id', $user->id)
            ->where('torrent_id', $torrent->id)
            ->first();

        $this->assertNotNull($userTorrent);
        $this->assertNotNull($userTorrent?->first_grab_at);
        $this->assertNotNull($userTorrent?->last_grab_at);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        Storage::fake('torrents');
        $torrent = Torrent::factory()->create();
        Storage::disk('torrents')->put($torrent->torrentStoragePath(), $this->makeTorrentPayload());

        $response = $this->get(route('torrents.download', $torrent));

        $response->assertStatus(302);
        $this->assertStringContainsString('login', (string) $response->headers->get('Location'));
    }

    public function test_banned_torrent_cannot_be_downloaded(): void
    {
        Storage::fake('torrents');
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['is_banned' => true]);
        Storage::disk('torrents')->put($torrent->torrentStoragePath(), $this->makeTorrentPayload());

        $response = $this->actingAs($user)->get(route('torrents.download', $torrent));
        $response->assertNotFound();
    }

    public function test_missing_file_returns_not_found(): void
    {
        Storage::fake('torrents');
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $response = $this->actingAs($user)->get(route('torrents.download', $torrent));

        $response->assertNotFound();
    }

    private function makeTorrentPayload(): string
    {
        return app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => 'Download demo',
                'length' => 1024,
            ],
        ]);
    }
}
