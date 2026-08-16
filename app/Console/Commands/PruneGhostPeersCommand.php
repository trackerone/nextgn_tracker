<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tracker\GhostPeerPruner;
use Illuminate\Console\Command;

final class PruneGhostPeersCommand extends Command
{
    protected $signature = 'tracker:prune-ghost-peers {--dry-run : Report expired peers without deleting them}';

    protected $description = 'Remove tracker peers that have not announced within the configured timeout.';

    public function handle(GhostPeerPruner $pruner): int
    {
        $timeoutMinutes = (int) config('tracker.ghost_peer_timeout_minutes');

        if ($timeoutMinutes < 1) {
            $this->error('TRACKER_GHOST_TIMEOUT_MINUTES must be at least 1.');

            return self::FAILURE;
        }

        $result = $pruner->prune(
            cutoff: now()->subMinutes($timeoutMinutes),
            dryRun: (bool) $this->option('dry-run'),
        );

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf(
                'Dry run: found %d ghost peer(s) across %d torrent(s).',
                $result['matched'],
                $result['affected_torrents'],
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Pruned %d ghost peer(s) across %d torrent(s).',
            $result['deleted'],
            $result['affected_torrents'],
        ));

        return self::SUCCESS;
    }
}
