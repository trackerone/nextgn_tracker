<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentDetailsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_rejected(): void
    {
        $torrent = Torrent::factory()->create();

        $this->getJson('/api/torrents/'.$torrent->id)->assertStatus(401);
    }

    public function test_authenticated_user_gets_torrent_details_payload(): void
    {
        $user = User::factory()->create();
        config()->set('tracker.announce_url', 'https://tracker.example/announce/%s');
        config()->set('tracker.additional_trackers', ['https://backup.example/announce']);
        $uploader = User::factory()->create(['name' => 'trackerone']);
        $category = Category::factory()->create(['name' => 'Movies', 'slug' => 'movies']);

        $torrent = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'category_id' => $category->id,
            'slug' => 'example-slug',
            'name' => 'Example Torrent',
            'description' => 'Example description',
            'type' => 'movie',
            'size_bytes' => 123_456,
            'seeders' => 10,
            'leechers' => 2,
            'completed' => 44,
            'freeleech' => false,
            'info_hash' => 'ABCDEF1234567890ABCDEF1234567890ABCDEF12',
            'uploaded_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id);

        $response->assertOk();
        $response->assertJsonPath('data.id', $torrent->id);
        $response->assertJsonPath('data.slug', 'example-slug');
        $response->assertJsonPath('data.name', 'Example Torrent');
        $response->assertJsonPath('data.description', 'Example description');
        $response->assertJsonPath('data.category.id', $category->id);
        $response->assertJsonPath('data.category.name', 'Movies');
        $response->assertJsonPath('data.category.slug', 'movies');
        $response->assertJsonPath('data.type', 'movie');
        $response->assertJsonPath('data.size_bytes', 123456);
        $response->assertJsonPath('data.size_human', '120.56 KiB');
        $response->assertJsonPath('data.seeders', 10);
        $response->assertJsonPath('data.leechers', 2);
        $response->assertJsonPath('data.completed', 44);
        $response->assertJsonPath('data.freeleech', false);
        $response->assertJsonPath('data.uploader.id', $uploader->id);
        $response->assertJsonPath('data.uploader.name', 'trackerone');
        $response->assertJsonPath('data.status', Torrent::STATUS_PUBLISHED);
        $this->assertIsString($response->json('data.status'));
        $response->assertJsonPath('data.info_hash', strtolower($torrent->info_hash));
        $response->assertJsonMissingPath('data.storage_path');
        $response->assertJsonMissingPath('data.user_id');
        $response->assertJsonMissingPath('data.moderated_by');
        $response->assertJsonPath('data.file_count', 0);
        $response->assertJsonPath('data.files', []);
        $response->assertJsonPath('data.download_url', '/api/torrents/'.$torrent->id.'/download');
        $response->assertJsonPath('data.magnet_url', sprintf(
            'magnet:?xt=urn:btih:%s&dn=%s&tr=%s&tr=%s',
            strtoupper($torrent->info_hash),
            rawurlencode($torrent->name),
            rawurlencode(sprintf('https://tracker.example/announce/%s', $user->passkey)),
            rawurlencode('https://backup.example/announce')
        ));

        $this->assertNotNull($response->json('data.uploaded_at'));
        $this->assertNotNull($response->json('data.uploaded_at_human'));
    }

    public function test_pending_torrent_returns_404_for_regular_user(): void
    {
        $user = User::factory()->create();
        $pending = Torrent::factory()->unapproved()->create();

        $this->actingAs($user)->getJson('/api/torrents/'.$pending->id)->assertNotFound();
    }

    public function test_rejected_torrent_returns_404_for_regular_user(): void
    {
        $user = User::factory()->create();
        $rejected = Torrent::factory()->rejected()->create();

        $this->actingAs($user)->getJson('/api/torrents/'.$rejected->id)->assertNotFound();
    }

    public function test_files_fallback_is_stable_when_no_file_relation_exists(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['file_count' => 2]);

        $response = $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id);

        $response->assertOk();
        $response->assertJsonPath('data.file_count', 0);
        $response->assertJsonPath('data.files', []);
    }

    public function test_details_payload_contains_non_empty_magnet_url_for_visible_torrent(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id);

        $response->assertOk();
        $this->assertNotSame('', (string) $response->json('data.magnet_url'));
    }
}
