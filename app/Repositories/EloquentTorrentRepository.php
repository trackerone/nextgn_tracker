<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TorrentRepositoryInterface;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentTorrentRepository implements TorrentRepositoryInterface
{
    public function paginateVisible(int $perPage = 50): LengthAwarePaginator
    {
        return Torrent::query()
            ->where('is_visible', true)
            ->latest()
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): ?Torrent
    {
        return Torrent::query()
            ->where('slug', $slug)
            ->first();
    }

    public function createForUser(User $user, array $attributes): Torrent
    {
        $payload = ['user_id' => $user->getKey()] + $attributes;

        return Torrent::query()->create($payload);
    }

    public function incrementStats(Torrent $torrent, array $stats): void
    {
        $allowedKeys = ['seeders', 'leechers', 'completed'];
        $updates = [];

        foreach ($stats as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                continue;
            }

            $increment = (int) $value;

            if ($increment === 0) {
                continue;
            }

            $updates[$key] = max(0, (int) $torrent->{$key} + $increment);
        }

        if ($updates === []) {
            return;
        }

        $torrent->forceFill($updates);
        $torrent->save();
    }
}
