<?php

declare(strict_types=1);

namespace Tests\Feature\Metadata;

use App\Actions\Torrents\PublishTorrentAction;
use App\Enums\TorrentStatus;
use App\Jobs\EnrichTorrentExternalMetadata;
use App\Models\SiteSetting;
use App\Models\Torrent;
use App\Models\TorrentExternalMetadata;
use App\Models\User;
use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataEnricher;
use App\Services\Metadata\ExternalMetadataConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ExternalMetadataFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_dispatches_enrichment_job_when_auto_on_publish_enabled(): void
    {
        Queue::fake();

        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.enrichment.auto_on_publish', 'true', 'bool');

        $moderator = User::factory()->create();
        $torrent = Torrent::factory()->unapproved()->create(['status' => TorrentStatus::Pending]);

        app(PublishTorrentAction::class)->execute($torrent, $moderator);

        Queue::assertPushed(EnrichTorrentExternalMetadata::class);
    }

    public function test_publish_does_not_dispatch_enrichment_job_when_auto_on_publish_disabled(): void
    {
        Queue::fake();

        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.enrichment.auto_on_publish', 'false', 'bool');

        $moderator = User::factory()->create();
        $torrent = Torrent::factory()->unapproved()->create(['status' => TorrentStatus::Pending]);

        app(PublishTorrentAction::class)->execute($torrent, $moderator);

        Queue::assertNotPushed(EnrichTorrentExternalMetadata::class);
    }

    public function test_enrichment_job_updates_torrent_external_metadata(): void
    {
        $this->setSiteSetting('metadata.enrichment.enabled', 'true', 'bool');
        $this->setSiteSetting('metadata.providers.priority', '["tmdb"]', 'json');
        $this->setSiteSetting('metadata.providers.tmdb.enabled', 'true', 'bool');

        $torrent = Torrent::factory()->create();

        $enricher = new ExternalMetadataEnricher(
            app(ExternalMetadataConfig::class),
            new FakeFlowExternalMetadataProvider(
                key: 'tmdb',
                result: new ExternalMetadataResult(
                    provider: 'tmdb',
                    found: true,
                    imdbId: 'tt1234567',
                    tmdbId: '123',
                    title: 'Flow Result',
                    year: 2020,
                    mediaType: 'movie',
                ),
            ),
            new FakeFlowExternalMetadataProvider('trakt', ExternalMetadataResult::skipped('trakt'), supports: false),
            new FakeFlowExternalMetadataProvider('imdb', ExternalMetadataResult::skipped('imdb'), supports: false),
        );

        $job = new EnrichTorrentExternalMetadata($torrent->id);
        $job->handle($enricher);

        $this->assertDatabaseHas('torrent_external_metadata', [
            'torrent_id' => $torrent->id,
            'enrichment_status' => 'enriched',
        ]);
    }

    public function test_torrent_api_resources_include_external_metadata_when_available(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        TorrentExternalMetadata::factory()->for($torrent)->create([
            'imdb_id' => 'tt1234567',
            'tmdb_id' => '123',
            'enrichment_status' => 'enriched',
        ]);

        $response = $this->actingAs($user)->getJson('/api/torrents/'.$torrent->id);

        $response->assertOk();
        $response->assertJsonPath('data.external_metadata.imdb_id', 'tt1234567');
        $response->assertJsonPath('data.external_metadata.tmdb_id', '123');
        $response->assertJsonPath('data.external_metadata.enrichment_status', 'enriched');
    }

    private function setSiteSetting(string $key, string $value, string $type): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type],
        );
    }
}

final class FakeFlowExternalMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(
        private readonly string $key,
        private readonly ExternalMetadataResult $result,
        private readonly bool $supports = true,
    ) {}

    public function providerKey(): string
    {
        return $this->key;
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $this->supports;
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        return $this->result;
    }
}
