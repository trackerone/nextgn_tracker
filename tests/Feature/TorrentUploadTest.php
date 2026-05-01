<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\UploadPreflightContext;
use App\Services\Torrents\UploadPreflightContextBuilderContract;
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
        $payload = $this->sampleTorrentPayload('Feature.Upload.2024.1080p.WEB-DL.x264-GRP', 2048);

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

        $torrent = Torrent::query()->first();

        $this->assertNotNull($torrent);
        $response->assertRedirect(route('torrents.show', $torrent->slug));
        $response->assertSessionHas('status', 'Torrent uploaded and awaiting approval.');
        $this->assertSame('movie', $torrent->type);
        $this->assertSame($category->id, $torrent->category_id);
        $this->assertSame('tt1234567', $torrent->imdb_id);
        $this->assertSame(9988, $torrent->tmdb_id);
        $this->assertSame(2048, $torrent->size_bytes);
        $this->assertSame(1, $torrent->file_count);

        $this->assertDatabaseHas('torrent_metadata', [
            'torrent_id' => $torrent->id,
            'type' => 'movie',
            'imdb_id' => 'tt1234567',
            'tmdb_id' => 9988,
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);
        Storage::disk('torrents')->assertExists($torrent->torrentStoragePath());
        $this->assertNotNull($torrent->nfoStoragePath());
        Storage::disk('nfo')->assertExists($torrent->nfoStoragePath());
    }

    public function test_successful_upload_displays_normalized_metadata_feedback_on_confirmation_page(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $payload = $this->sampleTorrentPayload('Feedback.Release.2024.1080p.WEB-DL.x264-GRP', 3072);

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->post(route('torrents.store'), [
                'name' => 'Feedback Release',
                'category_id' => $category->id,
                'type' => 'movie',
                'torrent_file' => UploadedFile::fake()->createWithContent('feedback.torrent', $payload, 'application/x-bittorrent'),
            ]);

        $response->assertOk();
        $response->assertSee('Normalized metadata extracted');
        $response->assertSee('Movie');
        $response->assertSee('1080p');
        $response->assertSee('WEB-DL');
        $response->assertSee('2024');
    }

    public function test_successful_upload_feedback_hides_empty_metadata_fields(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');

        $user = User::factory()->create();
        $payload = $this->sampleTorrentPayload('Feedback.Partial', 1024);

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->post(route('torrents.store'), [
                'name' => 'Feedback Partial',
                'type' => 'movie',
                'torrent_file' => UploadedFile::fake()->createWithContent('feedback-partial.torrent', $payload, 'application/x-bittorrent'),
            ]);

        $response->assertOk();
        $response->assertSee('Normalized metadata extracted');
        $response->assertSee('Movie');
        $response->assertDontSee('Release group');
        $response->assertDontSee('Year');
    }

    public function test_upload_prefers_extracted_source_over_request_source(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');

        $user = User::factory()->create();

        $payload = $this->sampleTorrentPayload('Priority.Title.2024.1080p.WEB-DL.x264-GRP', 1536);

        $this->actingAs($user)->post(route('torrents.store'), [
            'name' => 'Priority Title',
            'type' => 'movie',
            'source' => 'bluray',
            'resolution' => '1080p',
            'torrent_file' => UploadedFile::fake()->createWithContent('priority.torrent', $payload, 'application/x-bittorrent'),
        ])->assertRedirect();

        $torrent = Torrent::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('torrent_metadata', [
            'torrent_id' => $torrent->id,
            'source' => 'WEB-DL',
        ]);
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
        $response->assertSessionHas('status', 'Torrent already exists – redirected to the existing entry.');
    }

    public function test_ingest_duplicate_conflict_redirects_with_same_feedback_as_preflight_duplicate(): void
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

        $this->mock(UploadPreflightContextBuilderContract::class, function ($mock): void {
            $mock->shouldReceive('forPayload')->andReturn(new UploadPreflightContext(
                category: null,
                type: 'movie',
                resolution: null,
                scene: null,
                duplicate: false,
                size: 4096,
                isBanned: false,
                isDisabled: false,
                metadataComplete: true,
                infoHash: null,
                existingTorrentId: null,
            ));
        });

        $response = $this->actingAs($user)->post(route('torrents.store'), [
            'name' => 'Duplicate Upload 2',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('dup2.torrent', $payload, 'application/x-bittorrent'),
        ]);

        $response->assertRedirect(route('torrents.show', $existing->slug));
        $response->assertSessionHas('status', 'Torrent already exists – redirected to the existing entry.');
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

    public function test_upload_page_renders_upgrade_warning_when_preflight_has_upgrade_available(): void
    {
        $response = $this->view('torrents.upload', [
            'categories' => collect(),
            'releaseAdvice' => [
                'upgrade_available' => true,
                'best_version_torrent_id' => 1234,
                'best_version_is_current_upload' => false,
            ],
        ]);

        $response->assertOk();
        $response->assertSee('A better version already exists.');
        $response->assertSee('Best version torrent ID: 1234');
    }

    public function test_upload_page_hides_upgrade_warning_when_preflight_has_no_upgrade(): void
    {
        $response = $this->view('torrents.upload', [
            'categories' => collect(),
            'releaseAdvice' => [
                'upgrade_available' => false,
                'best_version_is_current_upload' => false,
            ],
        ]);

        $response->assertOk();
        $response->assertDontSee('A better version already exists.');
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
