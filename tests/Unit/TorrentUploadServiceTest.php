<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\Torrent;
use App\Models\User;
use App\Services\TorrentUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TorrentUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_parses_single_file_torrent(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $payload = 'd4:infod6:lengthi12345e4:name12:Example Fileee';
        $file = UploadedFile::fake()->createWithContent('example.torrent', $payload, 'application/x-bittorrent');

        $service = app(TorrentUploadService::class);
        $torrent = $service->handle($file, $user, ['description' => 'Test upload']);

        $this->assertSame('Example File', $torrent->name);
        $this->assertSame(12345, $torrent->size);
        $this->assertSame(1, $torrent->files_count);
        $this->assertFalse($torrent->is_approved);

        Storage::disk('local')->assertExists('torrents/'.$torrent->info_hash.'.torrent');
    }

    public function test_handle_rejects_duplicate_info_hash(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $payload = 'd4:infod6:lengthi555e4:name9:Duplicateee';
        $file = UploadedFile::fake()->createWithContent('duplicate.torrent', $payload, 'application/x-bittorrent');

        $service = app(TorrentUploadService::class);
        $service->handle($file, $user);

        try {
            $service->handle(
                UploadedFile::fake()->createWithContent('duplicate-2.torrent', $payload, 'application/x-bittorrent'),
                $user
            );

            $this->fail('Expected duplicate upload to throw.');
        } catch (TorrentAlreadyExistsException) {
            $this->assertSame(1, Torrent::query()->count());
        }
    }
}
