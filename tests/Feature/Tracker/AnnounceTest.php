<?php

declare(strict_types=1);

namespace Tests\Feature\Tracker;

use App\Models\Peer;
use App\Models\SecurityEvent;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AnnounceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_passkey_returns_failure(): void
    {
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-invalid-passkey');

        $params = [
            'info_hash' => $infoHashHex,
            'peer_id' => $this->makePeerIdHex('invalid-passkey'),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ];

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $response = $this->get(sprintf('/announce/%s?%s', 'not-a-real-passkey', $query));
        $response->assertOk();

        $expected = app(BencodeService::class)->encode([
            'failure reason' => 'Invalid passkey.',
        ]);

        $this->assertSame($expected, $response->getContent());
    }

    public function test_banned_or_disabled_user_passkey_is_rejected(): void
    {
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-user-state-rejected');

        $query = http_build_query([
            'info_hash' => $infoHashHex,
            'peer_id' => $this->makePeerIdHex('user-state-rejected'),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ], '', '&', PHP_QUERY_RFC3986);

        $bannedUser = User::factory()->create(['is_banned' => true]);
        $disabledUser = User::factory()->create(['is_disabled' => true]);

        $this->get(sprintf('/announce/%s?%s', $bannedUser->ensurePasskey(), $query))
            ->assertOk()
            ->assertSee('Invalid passkey.', false);

        $this->get(sprintf('/announce/%s?%s', $disabledUser->ensurePasskey(), $query))
            ->assertOk()
            ->assertSee('Invalid passkey.', false);
    }

    public function test_started_event_registers_peer_and_returns_payload(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MODERATOR,
        ]);

        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-started');
        $peerIdHex = $this->makePeerIdHex('peer-started');

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'left' => 100,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
        ]);

        $this->assertStringContainsString(
            '8:intervali1800e',
            (string) $response->getContent()
        );
    }

    public function test_banned_torrent_returns_failure(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
        ]);

        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-banned', [
            'is_banned' => true,
        ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('banned'),
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'Torrent is banned',
            (string) $response->getContent()
        );
    }

    public function test_unapproved_torrent_is_rejected_for_regular_users(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
        ]);

        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-unapproved', [
            'is_approved' => false,
            'status' => Torrent::STATUS_PENDING,
        ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('unapproved'),
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'Torrent is not approved yet',
            (string) $response->getContent()
        );
    }

    public function test_low_ratio_user_blocked_on_started_event(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-low-ratio');
        $peerIdHex = $this->makePeerIdHex('low-ratio');

        UserTorrent::factory()
            ->for($user)
            ->for($torrent)
            ->create([
                'uploaded' => 10,
                'downloaded' => 200,
            ]);

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 100,
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'ratio is too low',
            (string) $response->getContent()
        );
    }

    public function test_completed_event_sets_completed_at_once(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-complete-once');
        $peerIdHex = $this->makePeerIdHex('complete-once');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 100,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $snatch = UserTorrent::query()
            ->where('user_id', $user->id)
            ->where('torrent_id', $torrent->id)
            ->first();

        $this->assertNotNull($snatch);
        $this->assertNotNull($snatch->completed_at);

        $firstCompletedAt = $snatch->completed_at;

        $this->travel(5)->minutes();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $snatch->refresh();

        $this->assertSame(
            $firstCompletedAt->toDateTimeString(),
            $snatch->completed_at->toDateTimeString()
        );
    }

    public function test_stopped_event_removes_peer_and_updates_stats(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-stopped');
        $peerIdHex = $this->makePeerIdHex('stopped-peer');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
        ])->assertOk();

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
        ]);

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'stopped',
        ])->assertOk();

        $this->assertDatabaseMissing('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
        ]);
    }

    public function test_stopped_event_on_missing_peer_returns_success_without_creating_peer(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-stopped-missing');

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('missing-stopped-peer'),
            'event' => 'stopped',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('failure reason', (string) $response->getContent());
        $this->assertDatabaseCount('peers', 0);
        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
        ]);
    }

    public function test_peer_identity_is_scoped_to_user_for_matching_peer_ids(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-user-scoped-peer-id');
        $peerIdHex = $this->makePeerIdHex('shared-peer-id');
        $peerIdBinary = hex2bin($peerIdHex);

        $this->announce($userA, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 100,
            'downloaded' => 200,
            'left' => 300,
        ])->assertOk();

        $this->announce($userB, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 400,
            'downloaded' => 500,
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseCount('peers', 2);
        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'user_id' => $userA->id,
            'peer_id' => $peerIdBinary,
            'uploaded' => 100,
            'downloaded' => 200,
            'left' => 300,
        ]);
        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'user_id' => $userB->id,
            'peer_id' => $peerIdBinary,
            'uploaded' => 400,
            'downloaded' => 500,
            'left' => 0,
        ]);

        $this->announce($userB, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'stopped',
            'uploaded' => 400,
            'downloaded' => 500,
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseCount('peers', 1);
        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'user_id' => $userA->id,
            'peer_id' => $peerIdBinary,
            'uploaded' => 100,
            'downloaded' => 200,
            'left' => 300,
        ]);
        $this->assertDatabaseMissing('peers', [
            'torrent_id' => $torrent->id,
            'user_id' => $userB->id,
            'peer_id' => $peerIdBinary,
        ]);
    }

    public function test_announce_without_event_updates_existing_peer_and_left_zero_sets_seeder(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-no-event-update');
        $peerIdHex = $this->makePeerIdHex('peer-no-event');
        $peerIdBinary = hex2bin($peerIdHex);

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 5,
            'downloaded' => 10,
            'left' => 100,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 50,
            'downloaded' => 100,
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => $peerIdBinary,
            'uploaded' => 50,
            'downloaded' => 100,
            'left' => 0,
            'is_seeder' => true,
        ]);

        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded' => 50,
            'downloaded' => 100,
        ]);
    }

    public function test_completed_event_does_not_double_increment_torrent_completed_counter(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-completed-counter-once');
        $peerIdHex = $this->makePeerIdHex('peer-completed-counter');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 50,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $torrent->refresh();

        $this->assertSame(1, $torrent->completed);
    }

    public function test_completed_event_from_different_peer_ids_increments_counter_once(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-completed-counter-different-peers');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-completed-counter-one'),
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $firstCompletedAt = UserTorrent::query()
            ->where('user_id', $user->id)
            ->where('torrent_id', $torrent->id)
            ->value('completed_at');

        $this->travel(5)->minutes();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-completed-counter-two'),
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $torrent->refresh();

        $this->assertSame(1, $torrent->completed);
        $this->assertDatabaseCount('user_torrents', 1);
        $this->assertEquals(
            $firstCompletedAt,
            UserTorrent::query()
                ->where('user_id', $user->id)
                ->where('torrent_id', $torrent->id)
                ->value('completed_at')
        );
    }

    public function test_completed_event_sequence_with_existing_incomplete_snatch_is_idempotent(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-completed-counter-existing-snatch');

        UserTorrent::query()->create([
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded' => 0,
            'downloaded' => 0,
            'completed_at' => null,
        ]);

        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-existing-snatch-one'),
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-existing-snatch-two'),
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $torrent->refresh();

        $this->assertSame(1, $torrent->completed);
        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
        ]);

        $completedAt = UserTorrent::query()
            ->where('user_id', $user->id)
            ->where('torrent_id', $torrent->id)
            ->value('completed_at');

        $this->assertNotNull($completedAt);
    }

    public function test_ratio_stats_accumulate_only_positive_deltas(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-positive-deltas');
        $peerIdHex = $this->makePeerIdHex('peer-ratio-positive-deltas');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 100,
            'downloaded' => 50,
            'left' => 500,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 160,
            'downloaded' => 95,
            'left' => 300,
        ])->assertOk();

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'uploaded_bytes' => 60,
            'downloaded_bytes' => 45,
        ]);

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded_bytes' => 60,
            'downloaded_bytes' => 45,
        ]);
    }

    public function test_ratio_stats_first_announce_without_existing_peer_does_not_credit_bytes(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-first-announce-zero');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-ratio-first-announce-zero'),
            'event' => 'started',
            'uploaded' => 999,
            'downloaded' => 555,
            'left' => 100,
        ])->assertOk();

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
        ]);

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
        ]);
    }

    public function test_ratio_stats_on_freeleech_credit_upload_but_not_download(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-freeleech-credit', [
            'is_freeleech' => true,
        ]);
        $peerIdHex = $this->makePeerIdHex('peer-ratio-freeleech-credit');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 100,
            'downloaded' => 50,
            'left' => 500,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 160,
            'downloaded' => 95,
            'left' => 300,
        ])->assertOk();

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'uploaded_bytes' => 60,
            'downloaded_bytes' => 0,
        ]);

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded_bytes' => 60,
            'downloaded_bytes' => 0,
        ]);
    }

    public function test_ratio_stats_first_announce_on_freeleech_does_not_credit_fake_delta(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-freeleech-first-announce', [
            'is_freeleech' => true,
        ]);

        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-ratio-freeleech-first-announce'),
            'event' => 'started',
            'uploaded' => 999,
            'downloaded' => 555,
            'left' => 100,
        ])->assertOk();

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
        ]);

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
        ]);
    }

    public function test_ratio_stats_ignore_negative_deltas(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-negative-deltas');
        $peerIdHex = $this->makePeerIdHex('peer-ratio-negative-deltas');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 100,
            'downloaded' => 100,
            'left' => 100,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 10,
            'downloaded' => 20,
            'left' => 90,
        ])->assertOk();

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
        ]);

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
        ]);

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event_type' => 'tracker.announce.suspicious_delta',
        ]);
    }

    public function test_ratio_stats_ignore_negative_deltas_on_freeleech(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-freeleech-negative-deltas', [
            'is_freeleech' => true,
        ]);
        $peerIdHex = $this->makePeerIdHex('peer-ratio-freeleech-negative-deltas');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 100,
            'downloaded' => 100,
            'left' => 100,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 10,
            'downloaded' => 20,
            'left' => 90,
        ])->assertOk();

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
        ]);

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
        ]);
    }

    public function test_repeated_announce_with_same_counters_does_not_double_credit_ratio_stats(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-repeat-no-double-credit');
        $peerIdHex = $this->makePeerIdHex('peer-ratio-repeat-no-double-credit');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 100,
            'downloaded' => 50,
            'left' => 500,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 160,
            'downloaded' => 95,
            'left' => 300,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 160,
            'downloaded' => 95,
            'left' => 300,
        ])->assertOk();

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'uploaded_bytes' => 60,
            'downloaded_bytes' => 45,
        ]);

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded_bytes' => 60,
            'downloaded_bytes' => 45,
        ]);
    }

    public function test_completion_rollback_logs_specific_security_event(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-completion-rollback-log');
        $peerIdHex = $this->makePeerIdHex('peer-ratio-completion-rollback-log');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'left' => 100,
        ])->assertOk();

        $completionRollbackEvent = SecurityEvent::query()
            ->where('event_type', 'tracker.announce.completion_rollback')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($completionRollbackEvent);
        $this->assertSame(['completion_rollback'], $completionRollbackEvent->context['reasons'] ?? []);
    }

    public function test_ratio_stats_completion_transition_updates_completion_counters(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-completion-transition');
        $peerIdHex = $this->makePeerIdHex('peer-ratio-completion-transition');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 100,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'times_completed' => 1,
        ]);

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'completed_torrents_count' => 1,
        ]);
    }

    public function test_ratio_stats_repeated_complete_announce_does_not_increment_unique_completion_twice(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-ratio-complete-no-double-unique');
        $peerIdHex = $this->makePeerIdHex('peer-ratio-complete-no-double-unique');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 50,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('torrent_user_stats', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'times_completed' => 1,
        ]);

        $this->assertDatabaseHas('user_stats', [
            'user_id' => $user->id,
            'completed_torrents_count' => 1,
        ]);
    }

    public function test_malformed_info_hash_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->announce($user, 'abcd', [
            'info_hash' => 'short',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('info_hash must be exactly 20 bytes', (string) $response->getContent());
    }

    public function test_malformed_peer_id_is_rejected(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-malformed-peer-id');

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => 'short-peer-id',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('peer_id must be exactly 20 bytes', (string) $response->getContent());
    }

    public function test_banned_client_is_rejected_with_expected_failure_reason(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-banned-client');

        $response = $this->withHeader('User-Agent', 'BannedClient/1.0')
            ->get(sprintf(
                '/announce/%s?%s',
                $user->ensurePasskey(),
                http_build_query([
                    'info_hash' => strtolower($infoHashHex),
                    'peer_id' => $this->makePeerIdHex('banned-client-peer'),
                    'port' => 6881,
                    'uploaded' => 0,
                    'downloaded' => 0,
                    'left' => 100,
                ], '', '&', PHP_QUERY_RFC3986)
            ));

        $response->assertOk();
        $this->assertStringContainsString('Client is banned', (string) $response->getContent());
    }

    public function test_numwant_above_limit_is_clamped_instead_of_rejected(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-numwant-clamp');
        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-peer'),
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        for ($i = 0; $i < 210; $i++) {
            Peer::query()->create([
                'torrent_id' => $torrent->id,
                'user_id' => User::factory()->create()->id,
                'peer_id' => hex2bin($this->makePeerIdHex('peer-'.$i)),
                'ip' => sprintf('10.10.%d.%d', intdiv($i, 250), ($i % 250) + 1),
                'port' => 7000 + $i,
                'uploaded' => 0,
                'downloaded' => 0,
                'left' => 0,
                'is_seeder' => true,
                'last_announce_at' => now(),
            ]);
        }

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-peer'),
            'numwant' => 500,
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('must not be greater than 200', (string) $response->getContent());
    }

    public function test_numwant_zero_returns_success_with_no_peers(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-numwant-zero');
        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-numwant-zero'),
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        $otherUser = User::factory()->create();
        $this->announce($otherUser, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-numwant-zero'),
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-numwant-zero'),
            'numwant' => 0,
        ]);

        $response->assertOk();

        $payload = $this->decodeAnnouncePayload($response);
        $this->assertSame([], $payload['peers']);
    }

    public function test_numwant_missing_uses_default_cap_of_fifty(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-numwant-default');
        $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-numwant-default'),
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        for ($i = 0; $i < 75; $i++) {
            $otherUser = User::factory()->create();
            $this->announce($otherUser, $infoHashHex, [
                'peer_id' => $this->makePeerIdHex('peer-numwant-default-'.$i),
                'event' => 'started',
                'left' => 0,
            ])->assertOk();
        }

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('owner-numwant-default'),
        ]);

        $response->assertOk();

        $payload = $this->decodeAnnouncePayload($response);
        $this->assertCount(50, $payload['peers']);
    }

    public function test_exact_duplicate_no_event_announce_short_circuits_without_hidden_side_effects(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-duplicate-no-event');
        $requesterPeerIdHex = $this->makePeerIdHex('peer-duplicate-owner');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $requesterPeerIdHex,
            'event' => 'started',
            'uploaded' => 123,
            'downloaded' => 456,
            'left' => 10,
            'ip' => '10.2.0.1',
        ])->assertOk();

        $this->announce($otherUser, $infoHashHex, [
            'peer_id' => $this->makePeerIdHex('peer-duplicate-other'),
            'event' => 'started',
            'left' => 0,
            'ip' => '10.2.0.2',
        ])->assertOk();

        $userTorrentBefore = UserTorrent::query()
            ->where('user_id', $user->id)
            ->where('torrent_id', $torrent->id)
            ->firstOrFail();
        $peerBefore = $torrent->peers()
            ->where('peer_id', hex2bin($requesterPeerIdHex))
            ->firstOrFail();

        $response = $this->announce($user, $infoHashHex, [
            'peer_id' => $requesterPeerIdHex,
            'uploaded' => 123,
            'downloaded' => 456,
            'left' => 10,
            'ip' => '10.2.0.1',
            'numwant' => 10,
        ]);

        $response->assertOk();
        $payload = $this->decodeAnnouncePayload($response);
        $this->assertSame([], $payload['peers']);

        $peerAfter = $peerBefore->fresh();
        $this->assertNotNull($peerAfter);
        $this->assertSame($peerBefore->updated_at?->toDateTimeString(), $peerAfter->updated_at?->toDateTimeString());

        $userTorrentAfter = $userTorrentBefore->fresh();
        $this->assertNotNull($userTorrentAfter);
        $this->assertSame($userTorrentBefore->last_announce_at?->toDateTimeString(), $userTorrentAfter->last_announce_at?->toDateTimeString());
    }

    public function test_default_peer_list_order_is_deterministic_when_announces_share_timestamp(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-deterministic-order');
        $requesterPeerId = $this->makePeerIdHex('owner-deterministic-order');
        $firstPeerId = $this->makePeerIdHex('peer-a-deterministic-order');
        $secondPeerId = $this->makePeerIdHex('peer-b-deterministic-order');

        $this->travelTo(now()->startOfSecond());

        $this->announce($user, $infoHashHex, [
            'peer_id' => $requesterPeerId,
            'event' => 'started',
            'left' => 0,
            'ip' => '10.0.0.1',
        ])->assertOk();

        $firstUser = User::factory()->create();
        $this->announce($firstUser, $infoHashHex, [
            'peer_id' => $firstPeerId,
            'event' => 'started',
            'left' => 0,
            'ip' => '10.0.0.2',
        ])->assertOk();

        $secondUser = User::factory()->create();
        $this->announce($secondUser, $infoHashHex, [
            'peer_id' => $secondPeerId,
            'event' => 'started',
            'left' => 0,
            'ip' => '10.0.0.3',
        ])->assertOk();

        $firstResponse = $this->announce($user, $infoHashHex, [
            'peer_id' => $requesterPeerId,
            'numwant' => 2,
        ]);
        $secondResponse = $this->announce($user, $infoHashHex, [
            'peer_id' => $requesterPeerId,
            'uploaded' => 1,
            'numwant' => 2,
        ]);

        $this->travelBack();

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $firstPayload = $this->decodeAnnouncePayload($firstResponse);
        $secondPayload = $this->decodeAnnouncePayload($secondResponse);

        $this->assertSame($firstPayload['peers'], $secondPayload['peers']);
    }

    public function test_stopped_after_completed_keeps_completion_idempotent_and_removes_peer(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-stop-after-complete');
        $peerIdHex = $this->makePeerIdHex('peer-stop-after-complete');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'left' => 10,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'stopped',
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseMissing('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
        ]);

        $snatch = UserTorrent::query()
            ->where('user_id', $user->id)
            ->where('torrent_id', $torrent->id)
            ->first();

        $this->assertNotNull($snatch);
        $this->assertNotNull($snatch->completed_at);

        $torrent->refresh();
        $this->assertSame(1, $torrent->completed);
    }

    public function test_stopped_removes_only_target_peer_and_preserves_user_torrent_history(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-stopped-targeted');
        $firstPeerIdHex = $this->makePeerIdHex('peer-stopped-target-1');
        $secondPeerIdHex = $this->makePeerIdHex('peer-stopped-target-2');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $firstPeerIdHex,
            'event' => 'started',
            'uploaded' => 200,
            'downloaded' => 400,
            'left' => 20,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $secondPeerIdHex,
            'event' => 'started',
            'uploaded' => 350,
            'downloaded' => 700,
            'left' => 0,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $firstPeerIdHex,
            'event' => 'stopped',
            'uploaded' => 50,
            'downloaded' => 60,
            'left' => 20,
        ])->assertOk();

        $this->assertDatabaseMissing('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($firstPeerIdHex),
        ]);
        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($secondPeerIdHex),
            'is_seeder' => true,
        ]);
        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded' => 350,
            'downloaded' => 700,
        ]);
    }

    public function test_mixed_lifecycle_and_duplicates_keep_counters_coherent_across_multiple_peers(): void
    {
        $leecher = User::factory()->create();
        $seeder = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-mixed-invariants');
        $leecherPeerIdHex = $this->makePeerIdHex('peer-mixed-leecher');
        $seederPeerIdHex = $this->makePeerIdHex('peer-mixed-seeder');

        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'event' => 'started',
            'uploaded' => 100,
            'downloaded' => 200,
            'left' => 20,
            'ip' => '10.3.0.1',
        ])->assertOk();
        $this->announce($seeder, $infoHashHex, [
            'peer_id' => $seederPeerIdHex,
            'event' => 'started',
            'left' => 0,
            'ip' => '10.3.0.2',
        ])->assertOk();

        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'uploaded' => 100,
            'downloaded' => 200,
            'left' => 20,
            'ip' => '10.3.0.1',
        ])->assertOk();

        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'event' => 'completed',
            'uploaded' => 250,
            'downloaded' => 500,
            'left' => 0,
        ])->assertOk();
        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'event' => 'stopped',
            'uploaded' => 249,
            'downloaded' => 499,
            'left' => 0,
        ])->assertOk();
        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'event' => 'started',
            'left' => 0,
        ])->assertOk();
        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();

        $torrent->refresh();
        $this->assertSame(2, $torrent->seeders);
        $this->assertSame(0, $torrent->leechers);
        $this->assertSame(1, $torrent->completed);
    }

    public function test_started_then_no_event_updates_existing_peer_state(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-started-no-event-update');
        $peerIdHex = $this->makePeerIdHex('peer-started-no-event-update');

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 10,
            'downloaded' => 20,
            'left' => 200,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'uploaded' => 30,
            'downloaded' => 40,
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($peerIdHex),
            'uploaded' => 30,
            'downloaded' => 40,
            'left' => 0,
            'is_seeder' => true,
        ]);
    }

    public function test_uploaded_and_downloaded_are_monotonic_when_announces_send_lower_values(): void
    {
        $user = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-monotonic-traffic');
        $peerIdHex = $this->makePeerIdHex('peer-monotonic-traffic');
        $peerIdBinary = hex2bin($peerIdHex);

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'started',
            'uploaded' => 500,
            'downloaded' => 700,
            'left' => 100,
        ])->assertOk();

        $this->announce($user, $infoHashHex, [
            'peer_id' => $peerIdHex,
            'event' => 'completed',
            'uploaded' => 50,
            'downloaded' => 70,
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => $peerIdBinary,
            'uploaded' => 500,
            'downloaded' => 700,
            'left' => 0,
            'is_seeder' => true,
        ]);

        $this->assertDatabaseHas('user_torrents', [
            'user_id' => $user->id,
            'torrent_id' => $torrent->id,
            'uploaded' => 500,
            'downloaded' => 700,
        ]);
    }

    public function test_lifecycle_started_completed_stopped_keeps_counters_and_completion_consistent(): void
    {
        $leecher = User::factory()->create();
        $seeder = User::factory()->create();
        [$torrent, $infoHashHex] = $this->createTorrentWithInfoHashHex('torrent-lifecycle-consistency');
        $leecherPeerIdHex = $this->makePeerIdHex('peer-lifecycle-leecher');
        $seederPeerIdHex = $this->makePeerIdHex('peer-lifecycle-seeder');

        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'event' => 'started',
            'left' => 50,
        ])->assertOk();
        $this->announce($seeder, $infoHashHex, [
            'peer_id' => $seederPeerIdHex,
            'event' => 'started',
            'left' => 0,
        ])->assertOk();

        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'event' => 'completed',
            'left' => 0,
        ])->assertOk();
        $this->announce($leecher, $infoHashHex, [
            'peer_id' => $leecherPeerIdHex,
            'event' => 'stopped',
            'left' => 0,
        ])->assertOk();

        $this->assertDatabaseMissing('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($leecherPeerIdHex),
        ]);
        $this->assertDatabaseHas('peers', [
            'torrent_id' => $torrent->id,
            'peer_id' => hex2bin($seederPeerIdHex),
            'is_seeder' => true,
        ]);

        $snatch = UserTorrent::query()
            ->where('user_id', $leecher->id)
            ->where('torrent_id', $torrent->id)
            ->first();

        $this->assertNotNull($snatch);
        $this->assertNotNull($snatch->completed_at);

        $torrent->refresh();
        $this->assertSame(1, $torrent->seeders);
        $this->assertSame(0, $torrent->leechers);
        $this->assertSame(1, $torrent->completed);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAnnouncePayload(TestResponse $response): array
    {
        /** @var array<string, mixed> $payload */
        $payload = app(BencodeService::class)->decode((string) $response->getContent());

        return $payload;
    }

    private function announce(User $user, string $infoHashHex, array $overrides = []): TestResponse
    {
        $params = array_merge([
            // Send lowercase on wire to ensure robustness; repo normalizes to uppercase.
            'info_hash' => strtolower($infoHashHex),
            'peer_id' => $this->makePeerIdHex('default-peer'),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ], $overrides);

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $this->get(sprintf('/announce/%s?%s', $user->ensurePasskey(), $query));
    }

    private function createTorrentWithInfoHashHex(string $seed, array $overrides = []): array
    {
        // Repo expects uppercase lookup against torrents.info_hash
        $infoHashHex = $this->makeInfoHashHex($seed);

        $torrent = Torrent::factory()->create(array_merge([
            // Schema: string(40) unique
            'info_hash' => $infoHashHex,
        ], $overrides));

        return [$torrent, $infoHashHex];
    }

    private function makePeerIdHex(string $seed): string
    {
        return strtolower(
            bin2hex(substr(hash('sha1', $seed, true), 0, 20))
        );
    }

    private function makeInfoHashHex(string $seed): string
    {
        return strtoupper(sha1($seed));
    }
}
