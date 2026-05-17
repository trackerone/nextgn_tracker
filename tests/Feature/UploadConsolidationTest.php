<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class UploadConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_and_api_reject_same_invalid_torrent_extension_and_mime_with_expected_formats(): void
    {
        $payload = $this->sampleTorrentPayload();

        $webResponse = $this->actingAs(User::factory()->create())->post(route('torrents.store'), [
            'name' => 'Invalid Extension',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('invalid.txt', $payload, 'text/plain'),
        ]);

        $webResponse->assertSessionHasErrors(['torrent_file']);

        $apiResponse = $this->actingAs(User::factory()->create())->postJson(route('api.uploads.store'), [
            'name' => 'Invalid Extension',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('invalid.txt', $payload, 'text/plain'),
        ]);

        $apiResponse->assertUnprocessable();
        $apiResponse->assertJsonValidationErrors(['torrent_file']);
    }

    public function test_web_and_api_reject_nfo_file_and_inline_text_together_with_expected_formats(): void
    {
        $payload = $this->sampleTorrentPayload();

        $webResponse = $this->actingAs(User::factory()->create())->post(route('torrents.store'), [
            'name' => 'Invalid NFO',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('invalid-nfo.torrent', $payload, 'application/x-bittorrent'),
            'nfo_file' => UploadedFile::fake()->createWithContent('invalid.nfo', 'NFO FILE', 'text/plain'),
            'nfo_text' => 'NFO TEXT',
        ]);

        $webResponse->assertSessionHasErrors(['nfo_text']);

        $apiResponse = $this->actingAs(User::factory()->create())->postJson(route('api.uploads.store'), [
            'name' => 'Invalid NFO',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('invalid-nfo.torrent', $payload, 'application/x-bittorrent'),
            'nfo_file' => UploadedFile::fake()->createWithContent('invalid.nfo', 'NFO FILE', 'text/plain'),
            'nfo_text' => 'NFO TEXT',
        ]);

        $apiResponse->assertUnprocessable();
        $apiResponse->assertJsonValidationErrors(['nfo_text']);
    }

    public function test_web_and_api_enforce_same_torrent_type_enum_with_expected_formats(): void
    {
        $payload = $this->sampleTorrentPayload();

        $webResponse = $this->actingAs(User::factory()->create())->post(route('torrents.store'), [
            'name' => 'Invalid Type',
            'type' => 'documentary',
            'torrent_file' => UploadedFile::fake()->createWithContent('invalid-type.torrent', $payload, 'application/x-bittorrent'),
        ]);

        $webResponse->assertSessionHasErrors(['type']);

        $apiResponse = $this->actingAs(User::factory()->create())->postJson(route('api.uploads.store'), [
            'name' => 'Invalid Type',
            'type' => 'documentary',
            'torrent_file' => UploadedFile::fake()->createWithContent('invalid-type.torrent', $payload, 'application/x-bittorrent'),
        ]);

        $apiResponse->assertUnprocessable();
        $apiResponse->assertJsonValidationErrors(['type']);
    }

    public function test_cleanup_still_happens_when_metadata_persistence_fails(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');

        TorrentMetadata::creating(static function (): void {
            throw new RuntimeException('metadata persistence failed');
        });

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('metadata persistence failed');

        try {
            $this->actingAs(User::factory()->create())->post(route('torrents.store'), [
                'name' => 'Persistence Failure',
                'type' => 'movie',
                'torrent_file' => UploadedFile::fake()->createWithContent(
                    'persistence-failure.torrent',
                    $this->sampleTorrentPayload('Persistence Failure'),
                    'application/x-bittorrent'
                ),
                'nfo_text' => 'PRIVATE NFO',
            ]);
        } finally {
            $this->assertDatabaseCount('torrents', 0);
            $this->assertCount(0, Storage::disk('torrents')->allFiles());
            $this->assertCount(0, Storage::disk('nfo')->allFiles());
            TorrentMetadata::flushEventListeners();
        }
    }

    public function test_duplicate_upload_behavior_remains_unchanged_for_web_and_api(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');

        $payload = $this->sampleTorrentPayload('Duplicate Upload');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('torrents.store'), [
            'name' => 'Duplicate Upload',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('duplicate.torrent', $payload, 'application/x-bittorrent'),
        ])->assertRedirect();

        $torrent = Torrent::query()->firstOrFail();

        $this->actingAs($user)->post(route('torrents.store'), [
            'name' => 'Duplicate Upload',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('duplicate-again.torrent', $payload, 'application/x-bittorrent'),
        ])
            ->assertRedirect(route('torrents.show', $torrent->slug))
            ->assertSessionHas('status', 'Exact duplicate found; you were redirected to the existing torrent entry.');

        $this->actingAs($user)->postJson(route('api.uploads.store'), [
            'name' => 'Duplicate Upload',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('duplicate-api.torrent', $payload, 'application/x-bittorrent'),
        ])
            ->assertConflict()
            ->assertJsonPath('error', 'duplicate_torrent')
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('existing_torrent.id', $torrent->id)
            ->assertJsonPath('existing_torrent.slug', $torrent->slug);
    }

    private function sampleTorrentPayload(string $name = 'Upload Consolidation'): string
    {
        return app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => $name,
                'piece length' => 16384,
                'length' => 1024,
                'pieces' => str_repeat('u', 20),
            ],
        ]);
    }
}
