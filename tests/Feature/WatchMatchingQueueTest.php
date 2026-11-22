<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\MatchTorrentWatchPresets;
use App\Models\NotificationWatchPreset;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Services\Notifications\TorrentWatchPresetMatcher;
use App\Services\Torrents\CanonicalTorrentMetadata;
use App\Services\Torrents\PersistTorrentMetadataService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WatchMatchingQueueTest extends TestCase
{
    public function test_worker_rechecks_current_visibility_and_preset_state_and_does_not_duplicate_alerts(): void
    {
        $user = User::factory()->create();
        $preset = NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'filters' => ['q' => 'Nordic'],
            'is_enabled' => true,
        ]);
        $torrent = Torrent::factory()->create(['name' => 'Nordic Movie']);

        $job = new MatchTorrentWatchPresets((int) $torrent->id);
        $matcher = app(TorrentWatchPresetMatcher::class);

        $torrent->update(['is_visible' => false]);
        $job->handle($matcher);
        $this->assertDatabaseCount('torrent_watch_notifications', 0);

        $torrent->update(['is_visible' => true]);
        $preset->update(['is_enabled' => false]);
        $job->handle($matcher);
        $this->assertDatabaseCount('torrent_watch_notifications', 0);

        $preset->update(['is_enabled' => true]);
        $job->handle($matcher);
        $job->handle($matcher);
        $this->assertDatabaseCount('torrent_watch_notifications', 1);

        $torrent->update(['is_banned' => true]);
        $job->handle($matcher);
        $this->assertDatabaseCount('torrent_watch_notifications', 1);
    }

    public function test_visible_release_rematches_only_on_changes_to_watch_filter_metadata(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        NotificationWatchPreset::factory()->create([
            'user_id' => $user->id,
            'filters' => ['language' => 'da'],
        ]);
        $torrent = Torrent::factory()->create(['name' => 'Nordic Documentary']);
        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Nordic Documentary',
            'type' => 'movie',
            'language' => 'en',
        ]);

        $metadata = app(PersistTorrentMetadataService::class);

        $newFields = [
            'title' => 'Nordic Documentary',
            'type' => 'movie',
            'language' => 'da',
        ];

        $metadata->persist($torrent, CanonicalTorrentMetadata::fromArray($newFields));
        Queue::assertPushed(MatchTorrentWatchPresets::class, 1);

        // Job checks the *new* canonical metadata at processing time.
        (new MatchTorrentWatchPresets((int) $torrent->id))
            ->handle(app(TorrentWatchPresetMatcher::class));

        $this->assertDatabaseHas('torrent_watch_notifications', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
        ]);

        // Unchanged canonical data and enrichment-only identifiers do not
        // enqueue a redundant full-catalog watch-matching pass.
        $metadata->persist($torrent, CanonicalTorrentMetadata::fromArray($newFields));
        $metadata->persist($torrent, CanonicalTorrentMetadata::fromArray([
            ...$newFields,
            'imdb_id' => 'tt1234567',
        ]));

        Queue::assertPushed(MatchTorrentWatchPresets::class, 1);
        (new MatchTorrentWatchPresets((int) $torrent->id))
            ->handle(app(TorrentWatchPresetMatcher::class));
        $this->assertDatabaseCount('torrent_watch_notifications', 1);
    }

    public function test_pending_upload_metadata_never_queues_watch_notifications_before_approval(): void
    {
        Queue::fake();
        $pending = Torrent::factory()->unapproved()->create(['name' => 'Pending Documentary']);

        app(PersistTorrentMetadataService::class)->persist(
            $pending,
            CanonicalTorrentMetadata::fromArray([
                'title' => 'Pending Documentary',
                'type' => 'movie',
                'language' => 'da',
            ])
        );

        Queue::assertNotPushed(MatchTorrentWatchPresets::class);
        $this->assertDatabaseCount('torrent_watch_notifications', 0);
    }

    public function test_missing_or_removed_torrent_is_skipped_without_errors(): void
    {
        (new MatchTorrentWatchPresets(987654321))
            ->handle(app(TorrentWatchPresetMatcher::class));

        $this->assertDatabaseCount('torrent_watch_notifications', 0);
    }
}
