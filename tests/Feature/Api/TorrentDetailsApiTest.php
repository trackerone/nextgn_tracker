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
        $response->assertJsonPath('data.info_hash', strtolower($torrent->info_hash));
        $response->assertJsonPath('data.file_count', 0);
        $response->assertJsonPath('data.files', []);
        $response->assertJsonPath('data.magnet_url', 'magnet:?xt=urn:btih:'.strtolower($torrent->info_hash));
        $response->assertJsonPath('data.download_url', '/api/torrents/'.$torrent->id.'/download');

        $this->assertNotNull($response->json('data.uploaded_at'));
        $this->assertNotNull($response->json('data.uploaded_at_human'));
    }

    public function test_invisible_torrent_returns_404(): void
    {
        $user = User::factory()->create();
        $hidden = Torrent::factory()->unapproved()->create();

        $this->actingAs($user)->getJson('/api/torrents/'.$hidden->id)->assertNotFound();
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
}
