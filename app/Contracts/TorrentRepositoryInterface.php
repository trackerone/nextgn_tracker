<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TorrentRepositoryInterface
{
    public function paginateVisible(int $perPage = 50): LengthAwarePaginator;

    public function findBySlug(string $slug): ?Torrent;

    public function findByInfoHash(string $infoHash): ?Torrent;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForUser(User $user, array $attributes): Torrent;

    /**
     * @param  array<string, int>  $stats
     */
    public function incrementStats(Torrent $torrent, array $stats): void;

    public function refreshPeerStats(Torrent $torrent): void;
}
