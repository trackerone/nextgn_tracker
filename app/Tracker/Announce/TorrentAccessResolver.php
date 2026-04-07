<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\Request;

final class TorrentAccessResolver
{
    public function __construct(
        private readonly TorrentRepositoryInterface $torrents,
        private readonly AnnounceSecurityLogger $securityLogger,
        private readonly TwentyByteParamDecoder $decoder,
    ) {}

    public function resolve(Request $request, User $user, string $infoHashRaw): Torrent|AnnounceResult
    {
        $infoHash = $this->decoder->decode($infoHashRaw);
        if ($infoHash === null) {
            return AnnounceResult::failure('info_hash must be exactly 20 bytes.');
        }

        $infoHashHex = bin2hex($infoHash);

        $torrent = $this->torrents->findByInfoHash(strtolower($infoHashHex))
            ?? $this->torrents->findByInfoHash(strtoupper($infoHashHex));

        if (! $torrent instanceof Torrent) {
            return AnnounceResult::failure('Invalid info_hash.');
        }

        $isStaff = $this->isStaff($user);

        if ($torrent->isBanned() && ! $isStaff) {
            $this->securityLogger->log(
                request: $request,
                user: $user,
                eventType: 'tracker.torrent_banned',
                severity: 'high',
                message: 'Announce attempted on banned torrent',
                context: ['torrent_id' => $torrent->getKey()],
            );

            return AnnounceResult::failure('Torrent is banned.');
        }

        if (! $torrent->isApproved() && ! $isStaff) {
            return AnnounceResult::failure('Torrent is not approved yet.');
        }

        return $torrent;
    }

    private function isStaff(User $user): bool
    {
        return $user->isStaff()
            || in_array((string) ($user->role ?? ''), [
                'moderator',
                'admin',
                'sysop',
                'mod1',
                'mod2',
                'admin1',
                'admin2',
            ], true);
    }
}
