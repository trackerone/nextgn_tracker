<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\TorrentUploadController;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LocalFunctionTestRegressionTest extends TestCase
{
    public function test_upload_url_resolves_to_create_instead_of_torrent_show(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/torrents/upload', 'GET'));
        $this->assertSame(TorrentUploadController::class.'@create', $route->getActionName());
        $this->actingAs(User::factory()->create())->get('/torrents/upload')->assertOk();
    }

    public function test_analysis_accepts_dos_nfo_and_does_not_create_a_torrent(): void
    {
        Storage::fake('torrents');
        $response = $this->actingAs(User::factory()->create())->postJson(route('torrents.upload.analyse'), [
            'torrent_file' => $this->torrentFile(),
            'nfo_file' => UploadedFile::fake()->createWithContent('test.nfo', $this->legacyNfo())->mimeType('text/plain'),
        ]);

        $response->assertOk()
            ->assertJsonPath('fields.resolution', '1080p')
            ->assertJsonPath('fields.source', 'BLURAY')
            ->assertJsonPath('fields.codecs[video]', 'HEVC')
            ->assertJsonPath('fields.codecs[audio]', 'AC-3')
            ->assertJsonPath('fields.audio_language', 'en')
            ->assertJsonPath('fields.subtitles', 'da, fi, no, sv')
            ->assertJsonPath('files', 3);
        $this->assertDatabaseCount('torrents', 0);
        $this->assertDatabaseCount('upload_eligibility_events', 0);
        $this->assertSame([], Storage::disk('torrents')->allFiles());
    }

    public function test_analysis_requires_authentication_and_valid_files(): void
    {
        $this->postJson(route('torrents.upload.analyse'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->postJson(route('torrents.upload.analyse'), [])
            ->assertUnprocessable()->assertJsonValidationErrors('torrent_file');
        $this->postJson(route('torrents.upload.analyse'), [
            'torrent_file' => $this->torrentFile(),
            'nfo_file' => UploadedFile::fake()->createWithContent('notes.nfo', 'Notes')->mimeType('text/plain'),
            'nfo_text' => 'Also notes',
        ])->assertUnprocessable()->assertJsonValidationErrors('nfo_text');
    }

    public function test_dos_nfo_upload_keeps_detected_metadata_despite_empty_form_fields(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');
        $response = $this->actingAs(User::factory()->create())->post(route('torrents.store'), [
            'name' => 'Example Collection',
            'type' => 'movie',
            'title' => null,
            'year' => null,
            'release_group' => null,
            'audio_language' => null,
            'subtitles' => null,
            'torrent_file' => $this->torrentFile(),
            'nfo_file' => UploadedFile::fake()->createWithContent('test.nfo', $this->legacyNfo())->mimeType('text/plain'),
        ]);

        $response->assertRedirect();
        $torrent = Torrent::query()->firstOrFail();
        $metadata = TorrentMetadataView::forTorrent($torrent, includeCodecs: true);
        $this->assertSame(3, $torrent->file_count);
        $this->assertSame(3005, $torrent->size_bytes);
        $this->assertSame('en', $metadata['audio_language']);
        $this->assertSame('da, fi, no, sv', $metadata['subtitles']);
        $this->assertSame('HEVC', $metadata['video_codec']);
        $this->assertSame('AC-3', $metadata['audio_codec']);
        $this->assertNotEmpty($metadata['title']);
        $this->assertNull($metadata['imdb_id']);
        $this->assertNull($metadata['year']);
        $this->assertTrue(mb_check_encoding(json_encode($metadata, JSON_THROW_ON_ERROR), 'UTF-8'));

        $this->get(route('torrents.show', $torrent->slug))
            ->assertOk()->assertSee('Torrent pending')->assertDontSee('Approved torrent');
        $stored = Storage::disk('torrents')->get($torrent->torrentStoragePath());
        $hash = $torrent->info_hash;
        $torrent->forceFill(['file_count' => 6, 'files_count' => 6, 'size_bytes' => 3605])->save();
        $this->artisan('torrents:refresh-file-stats')->assertSuccessful();
        $this->assertSame(6, $torrent->fresh()->file_count);
        $this->artisan('torrents:refresh-file-stats --apply')->assertSuccessful();
        $this->assertSame(3, $torrent->fresh()->file_count);
        $this->assertSame(3005, $torrent->fresh()->size_bytes);
        $this->assertSame($hash, $torrent->fresh()->info_hash);
        $this->assertSame($stored, Storage::disk('torrents')->get($torrent->torrentStoragePath()));
    }

    public function test_reviewed_corrections_are_preserved(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');
        $this->actingAs(User::factory()->create())->post(route('torrents.store'), [
            'name' => 'Corrected release',
            'type' => 'movie',
            'metadata_reviewed' => '1',
            'source' => 'WEB-DL',
            'resolution' => '720p',
            'torrent_file' => $this->torrentFile(),
        ])->assertRedirect();

        $metadata = TorrentMetadataView::forTorrent(Torrent::query()->firstOrFail());
        $this->assertSame('WEB-DL', $metadata['source']);
        $this->assertSame('720p', $metadata['resolution']);
    }

    private function torrentFile(): UploadedFile
    {
        $payload = app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => 'Example.Collection.1080p.BluRay.x265-TEST',
                'private' => 1,
                'piece length' => 16384,
                'pieces' => str_repeat('a', 20),
                'files' => [
                    ['path' => ['one.mkv'], 'length' => 1000],
                    ['path' => ['.pad', '100'], 'length' => 100, 'attr' => 'p'],
                    ['path' => ['two.mkv'], 'length' => 2000],
                    ['path' => ['.pad', '200'], 'length' => 200],
                    ['path' => ['release.nfo'], 'length' => 5],
                    ['path' => ['padding'], 'length' => 300, 'attr' => 'p'],
                ],
            ],
        ]);

        return UploadedFile::fake()->createWithContent('test.torrent', $payload, 'application/x-bittorrent');
    }

    private function legacyNfo(): string
    {
        return str_repeat("\xDB", 12)."\nFIRST FILM (2016)\nSOURCE: BluRay\nFORMAT: HEVC 10bit\nAUDiO LANG: ENG.\nAUDiO iNFO: AC-3 384 Kbps\nSUBTiTLES: DAN. FIN. NOR. SWE.\nIMDb: tt1234567\nSECOND FILM (2018)\nIMDb: tt7654321";
    }
}
