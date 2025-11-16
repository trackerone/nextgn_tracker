<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TorrentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_is_required(): void
    {
        $response = $this->get(route('torrents.upload'));
        $response->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));

        $postResponse = $this->post(route('torrents.store'));
        $postResponse->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $postResponse->headers->get('Location'));
    }

    public function test_successful_upload_persists_torrent_and_file(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $payload = $this->sampleTorrentPayload('Feature Upload', 2048);

        $response = $this->actingAs($user)->post(route('torrents.store'), [
            'name' => 'Feature Upload',
            'category_id' => $category->id,
            'type' => 'movie',
            'description' => 'Plot with **markdown**',
            'tags_input' => 'action, thriller',
            'source' => 'bluray',
            'resolution' => '1080p',
            'codecs' => ['video' => 'x264', 'audio' => 'DTS'],
            'torrent_file' => UploadedFile::fake()->createWithContent('feature.torrent', $payload, 'application/x-bittorrent'),
            'nfo_text' => "IMDB: tt1234567\nhttps://www.themoviedb.org/movie/9988",
        ]);

        $response->assertRedirect();
        $torrent = Torrent::query()->first();

        $this->assertNotNull($torrent);
        $this->assertSame('movie', $torrent->type);
        $this->assertSame($category->id, $torrent->category_id);
        $this->assertSame('tt1234567', $torrent->imdb_id);
        $this->assertSame('9988', $torrent->tmdb_id);
        $this->assertSame(2048, $torrent->size_bytes);
        $this->assertSame(1, $torrent->file_count);
        Storage::disk('torrents')->assertExists($torrent->torrentStoragePath());
        $this->assertNotNull($torrent->nfoStoragePath());
        Storage::disk('nfo')->assertExists($torrent->nfoStoragePath());
    }

    public function test_duplicate_info_hash_redirects_to_existing_record(): void
    {
        Storage::fake('torrents');

        $user = User::factory()->create();
        $payload = $this->sampleTorrentPayload('Duplicate Upload', 4096);

        $this->actingAs($user)->post(route('torrents.store'), [
            'name' => 'Duplicate Upload',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('dup.torrent', $payload, 'application/x-bittorrent'),
        ])->assertRedirect();

        $existing = Torrent::query()->firstOrFail();

        $response = $this->actingAs($user)->post(route('torrents.store'), [
            'name' => 'Duplicate Upload 2',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('dup2.torrent', $payload, 'application/x-bittorrent'),
        ]);

        $response->assertRedirect(route('torrents.show', $existing->slug));
    }

    public function test_validation_errors_are_reported(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('torrents.store'), [
            'name' => '',
            'type' => 'movie',
        ]);

        $response->assertSessionHasErrors(['name', 'torrent_file']);
    }

    private function sampleTorrentPayload(string $name, int $length): string
    {
        $bencode = app(\App\Services\BencodeService::class);

        return $bencode->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => $name,
                'piece length' => 16384,
                'length' => $length,
                'pieces' => str_repeat('a', 20),
            ],
        ]);
    }
}
