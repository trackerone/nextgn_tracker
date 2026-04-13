<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TorrentUploadApiResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_submission_response_is_minimal_and_stable(): void
    {
        Storage::fake('torrents');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/uploads', [
            'name' => 'API Upload',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent(
                'api-upload.torrent',
                $this->sampleTorrentPayload(),
                'application/x-bittorrent'
            ),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'API Upload');
        $response->assertJsonPath('data.status', TorrentStatus::Pending->value);
        $this->assertIsString($response->json('data.status'));
        $response->assertJsonMissingPath('data.info_hash');
        $response->assertJsonMissingPath('data.storage_path');
        $response->assertJsonMissingPath('data.user_id');
    }

    public function test_my_uploads_response_exposes_only_expected_fields(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'user_id' => $user->id,
            'status' => Torrent::STATUS_REJECTED,
            'is_approved' => false,
            'moderated_reason' => 'Metadata mismatch',
        ]);

        $response = $this->actingAs($user)->getJson('/api/my/uploads');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $torrent->id);
        $response->assertJsonPath('data.0.slug', $torrent->slug);
        $response->assertJsonPath('data.0.name', $torrent->name);
        $response->assertJsonPath('data.0.status', TorrentStatus::Rejected->value);
        $response->assertJsonPath('data.0.moderation_reason', 'Metadata mismatch');
        $this->assertIsString($response->json('data.0.status'));

        $response->assertJsonMissingPath('data.0.info_hash');
        $response->assertJsonMissingPath('data.0.storage_path');
        $response->assertJsonMissingPath('data.0.user_id');
    }

    public function test_duplicate_upload_returns_conflict_with_machine_readable_error_payload(): void
    {
        Storage::fake('torrents');

        $user = User::factory()->create();
        $payload = $this->sampleTorrentPayload();

        $this->actingAs($user)->postJson('/api/uploads', [
            'name' => 'API Upload',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent(
                'api-upload.torrent',
                $payload,
                'application/x-bittorrent'
            ),
        ])->assertCreated();

        $response = $this->actingAs($user)->postJson('/api/uploads', [
            'name' => 'API Upload Duplicate',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent(
                'api-upload-duplicate.torrent',
                $payload,
                'application/x-bittorrent'
            ),
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Torrent already exists.');
        $response->assertJsonPath('error', 'duplicate_torrent');
    }

    private function sampleTorrentPayload(): string
    {
        return app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => 'API Upload',
                'piece length' => 16384,
                'length' => 1024,
                'pieces' => str_repeat('a', 20),
            ],
        ]);
    }
}
