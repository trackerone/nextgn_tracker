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
        $publishedAt = now()->subDay();

        $torrent = Torrent::factory()->create([
            'status' => TorrentStatus::Pending,
            'is_approved' => false,
            'published_at' => $publishedAt,
            'moderated_reason' => 'Legacy metadata',
        ]);

        app(PublishTorrentAction::class)->execute($torrent, $moderator);

        $torrent->refresh();

        $this->assertSame(TorrentStatus::Published, $torrent->status);
        $this->assertTrue($torrent->is_approved);
        $this->assertTrue($publishedAt->equalTo($torrent->published_at));
        $this->assertSame('Legacy metadata', $torrent->moderated_reason);
    }

    public function test_pending_to_rejected_succeeds(): void
    {
        $moderator = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'status' => TorrentStatus::Pending,
            'is_approved' => false,
        ]);

        app(RejectTorrentAction::class)->execute($torrent, $moderator, 'Invalid metadata');

        $torrent->refresh();

        $this->assertSame(TorrentStatus::Rejected, $torrent->status);
        $this->assertSame('Invalid metadata', $torrent->moderated_reason);
        $this->assertFalse($torrent->is_approved);
        $this->assertNull($torrent->published_at);
    }

    public function test_published_cannot_be_published_again(): void
    {
        $moderator = User::factory()->create();

        $published = Torrent::factory()->create([
            'status' => TorrentStatus::Published,
            'is_approved' => true,
        ]);

        $this->expectException(InvalidTorrentStatusTransitionException::class);
        app(PublishTorrentAction::class)->execute($published, $moderator);
    }

    public function test_rejected_cannot_be_rejected_again(): void
    {
        $moderator = User::factory()->create();

        $rejected = Torrent::factory()->create([
            'status' => TorrentStatus::Rejected,
            'is_approved' => false,
        ]);

        $this->expectException(InvalidTorrentStatusTransitionException::class);
        app(RejectTorrentAction::class)->execute($rejected, $moderator, 'Duplicate');
    }
}
