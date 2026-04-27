<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\Torrents\CanonicalTorrentMetadata;
use App\Services\Torrents\UploadReleaseAdvisor;
use App\Services\Torrents\UploadPreflightContext;
use App\Services\Torrents\UploadPreflightContextBuilderContract;
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
        $response->assertJsonStructure([
            'data' => ['id', 'slug', 'name', 'status'],
        ]);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertSame(
            ['id', 'slug', 'name', 'status'],
            array_keys($data)
        );
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
        $existing = Torrent::query()->firstOrFail();

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Torrent already exists.');
        $response->assertJsonPath('error', 'duplicate_torrent');
        $response->assertJsonPath('duplicate', true);
        $response->assertJsonPath('existing_torrent.id', $existing->getKey());
        $response->assertJsonPath('existing_torrent.slug', $existing->slug);
    }

    public function test_ingest_duplicate_conflict_uses_same_api_contract_as_preflight_duplicate(): void
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

        $this->mock(UploadPreflightContextBuilderContract::class, function ($mock): void {
            $mock->shouldReceive('forPayload')->andReturn(new UploadPreflightContext(
                category: null,
                type: 'movie',
                resolution: null,
                scene: null,
                duplicate: false,
                size: 1024,
                isBanned: false,
                isDisabled: false,
                metadataComplete: true,
                infoHash: null,
                existingTorrentId: null,
            ));
        });

        $response = $this->actingAs($user)->postJson('/api/uploads', [
            'name' => 'API Upload Duplicate',
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent(
                'api-upload-duplicate.torrent',
                $payload,
                'application/x-bittorrent'
            ),
        ]);

        $existing = Torrent::query()->firstOrFail();

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Torrent already exists.');
        $response->assertJsonPath('error', 'duplicate_torrent');
        $response->assertJsonPath('duplicate', true);
        $response->assertJsonPath('existing_torrent.id', $existing->getKey());
        $response->assertJsonPath('existing_torrent.slug', $existing->slug);
    }

    public function test_preflight_duplicate_response_includes_release_advice(): void
    {
        Storage::fake('torrents');

        $user = User::factory()->create();
        $payload = $this->sampleTorrentPayload();

        $this->actingAs($user)->postJson('/api/uploads', [
            'name' => 'API Upload',
            'type' => 'movie',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'torrent_file' => UploadedFile::fake()->createWithContent(
                'api-upload.torrent',
                $payload,
                'application/x-bittorrent'
            ),
        ])->assertCreated();

        $this->mock(UploadReleaseAdvisor::class, function ($mock): void {
            $mock->shouldReceive('advise')
                ->once()
                ->andReturn([
                    'family_key' => 'movie:api upload:2024',
                    'quality_score' => 720,
                    'family_exists' => true,
                    'same_quality_exists' => false,
                    'better_version_exists' => true,
                    'best_torrent_id' => 1,
                    'matching_torrent_ids' => [1],
                    'warnings' => ['same_family_exists', 'better_version_exists'],
                ]);
        });

        $response = $this->actingAs($user)->postJson('/api/uploads', [
            'name' => 'API Upload Duplicate',
            'type' => 'movie',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'torrent_file' => UploadedFile::fake()->createWithContent(
                'api-upload-duplicate.torrent',
                $payload,
                'application/x-bittorrent'
            ),
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('release_advice.family_exists', true);
        $response->assertJsonPath('release_advice.better_version_exists', true);
        $response->assertJsonPath('release_advice.warnings.0', 'same_family_exists');
        $response->assertJsonPath('release_advice.warnings.1', 'better_version_exists');
    }

    public function test_upload_submit_is_not_blocked_by_release_advice_warnings(): void
    {
        Storage::fake('torrents');

        $user = User::factory()->create();

        $this->mock(UploadReleaseAdvisor::class, function ($mock): void {
            $mock->shouldReceive('advise')
                ->andReturnUsing(static fn (CanonicalTorrentMetadata $metadata): array => [
                    'family_key' => 'movie:'.strtolower((string) $metadata->title).':2024',
                    'quality_score' => 610,
                    'family_exists' => true,
                    'same_quality_exists' => true,
                    'better_version_exists' => true,
                    'best_torrent_id' => 123,
                    'matching_torrent_ids' => [123],
                    'warnings' => ['same_family_exists', 'same_quality_exists', 'better_version_exists'],
                ]);
        });

        $response = $this->actingAs($user)->postJson('/api/uploads', [
            'name' => 'API Upload Advisory',
            'type' => 'movie',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'torrent_file' => UploadedFile::fake()->createWithContent(
                'api-upload-advisory.torrent',
                $this->sampleTorrentPayload(),
                'application/x-bittorrent'
            ),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'API Upload Advisory');
        $response->assertJsonPath('release_advice.family_exists', true);
        $response->assertJsonPath('release_advice.better_version_exists', true);
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
