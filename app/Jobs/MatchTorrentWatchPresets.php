<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Torrent;
use App\Services\Notifications\TorrentWatchPresetMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class MatchTorrentWatchPresets implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $torrentId) {}

    public function handle(TorrentWatchPresetMatcher $matcher): void
    {
        // Resolve the current torrent and permissions when the worker runs.
        // Deleted, hidden, banned or unapproved torrents never create alerts.
        $torrent = Torrent::query()->with('metadata')->find($this->torrentId);

        if (! $torrent instanceof Torrent || ! $torrent->isVisible()) {
            return;
        }

        // Unique (user, torrent, preset) rows make retries and rematches
        // idempotent; the matcher checks current user eligibility per preset.
        $matcher->notifyMatchesForTorrent($torrent);
    }
}
