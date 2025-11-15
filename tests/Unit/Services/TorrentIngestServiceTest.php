<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\User;
use App\Services\Torrents\TorrentIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TorrentIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingest_stores_file_and_metadata(): void
    {
        Storage::fake('torrents');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'movie.torrent',
            $this->sampleTorrentPayload([
                'name' => 'Example Release',
                'length' => 4096,
            ]),
            'application/x-bittorrent'
        );

        $service = app(TorrentIngestService::class);
        $torrent = $service->ingest($user, $file, [
            'name' => 'Example Release <b>deluxe</b>',
            'type' => 'movie',
            'description' => '<script>alert(1)</script>Plot',
            'tags' => ['scene', '<b>tag</b>'],
            'codecs' => ['video' => 'x264', 'audio' => 'DTS'],
        ]);

        $this->assertSame('Example Release deluxe', $torrent->name);
        $this->assertSame(4096, $torrent->size_bytes);
        $this->assertSame(1, $torrent->file_count);
        $this->assertStringNotContainsString('<script>', $torrent->description ?? '');
        $this->assertContains('scene', $torrent->tags ?? []);
        Storage::disk('torrents')->assertExists($torrent->torrentStoragePath());
    }

    public function test_ingest_detects_duplicate_hash(): void
    {
        Storage::fake('torrents');

        $user = User::factory()->create();
        $payload = $this->sampleTorrentPayload([
            'name' => 'Dup',
            'length' => 5120,
        ]);

        $service = app(TorrentIngestService::class);
        $service->ingest($user, UploadedFile::fake()->createWithContent(
            'dup.torrent',
            $payload,
            'application/x-bittorrent'
        ), [
            'name' => 'Dup',
            'type' => 'movie',
        ]);

        $this->expectException(TorrentAlreadyExistsException::class);

        $service->ingest($user, UploadedFile::fake()->createWithContent(
            'dup-2.torrent',
            $payload,
            'application/x-bittorrent'
        ), [
            'name' => 'Dup',
            'type' => 'movie',
        ]);
    }

    /**
     * @param  array<string, mixed>  $infoOverrides
     */
    private function sampleTorrentPayload(array $infoOverrides): string
    {
        $info = array_merge([
            'name' => 'Sample',
            'piece length' => 16384,
            'length' => 1024,
            'pieces' => str_repeat('a', 20),
        ], $infoOverrides);

        $bencode = app(\App\Services\BencodeService::class);

        return $bencode->encode([
            'announce' => 'http://localhost/announce',
            'info' => $info,
        ]);
    }
}
