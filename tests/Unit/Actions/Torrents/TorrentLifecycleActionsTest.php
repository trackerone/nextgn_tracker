<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Torrents;

use App\Actions\Torrents\PublishTorrentAction;
use App\Actions\Torrents\RejectTorrentAction;
use App\Enums\TorrentStatus;
use App\Exceptions\InvalidTorrentStatusTransitionException;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentLifecycleActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_to_published_succeeds_and_preserves_existing_metadata(): void
    {
        $moderator = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'status' => TorrentStatus::Pending,
            'is_approved' => false,
            'published_at' => now()->subDay()->startOfSecond(),
            'moderated_reason' => 'Legacy metadata',
        ]);

        $originalPublishedAt = $torrent->published_at->copy();

        $action = app(PublishTorrentAction::class);
        $action->execute($torrent, $moderator);

        $torrent->refresh();

        $this->assertSame(TorrentStatus::Published, $torrent->status);
        $this->assertTrue($torrent->is_approved);
        $this->assertTrue($originalPublishedAt->equalTo($torrent->published_at));
        $this->assertSame('Legacy metadata', $torrent->moderated_reason);
        $this->assertSame($moderator->id, $torrent->moderated_by);
        $this->assertNotNull($torrent->moderated_at);
    }

    public function test_pending_to_rejected_succeeds(): void
    {
        $moderator = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'status' => TorrentStatus::Pending,
            'is_approved' => false,
            'published_at' => null,
            'moderated_reason' => null,
        ]);

        $action = app(RejectTorrentAction::class);
        $action->execute($torrent, $moderator, 'Rejected for test');

        $torrent->refresh();

        $this->assertSame(TorrentStatus::Rejected, $torrent->status);
        $this->assertFalse($torrent->is_approved);
        $this->assertNull($torrent->published_at);
        $this->assertSame('Rejected for test', $torrent->moderated_reason);
        $this->assertSame($moderator->id, $torrent->moderated_by);
        $this->assertNotNull($torrent->moderated_at);
    }

    public function test_published_cannot_be_published_again(): void
    {
        $moderator = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'status' => TorrentStatus::Published,
            'is_approved' => true,
            'published_at' => now()->subDay()->startOfSecond(),
        ]);

        $action = app(PublishTorrentAction::class);

        $this->expectException(InvalidTorrentStatusTransitionException::class);

        $action->execute($torrent, $moderator);
    }

    public function test_rejected_cannot_be_rejected_again(): void
    {
        $moderator = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'status' => TorrentStatus::Rejected,
            'is_approved' => false,
        ]);

        $action = app(RejectTorrentAction::class);

        $this->expectException(InvalidTorrentStatusTransitionException::class);

        $action->execute($torrent, $moderator, 'Still rejected');
    }
}
