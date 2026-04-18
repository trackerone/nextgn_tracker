<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Models\TorrentFollow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class TorrentFollowMatcher
{
    /**
     * @param  Collection<int, TorrentFollow>  $follows
     * @return array<int, Collection<int, Torrent>>
     */
    public function matchesForFollows(Collection $follows): array
    {
        if ($follows->isEmpty()) {
            return [];
        }

        $torrents = Torrent::query()
            ->visible()
            ->with('metadata')
            ->orderByDesc('id')
            ->get();

        /** @var array<int, Collection<int, Torrent>> $matches */
        $matches = [];

        foreach ($follows as $follow) {
            $matched = $torrents->filter(fn (Torrent $torrent): bool => $this->matchesFollow($follow, $torrent))->values();
            $matches[(int) $follow->id] = $matched;
        }

        return $matches;
    }

    public function matchesFollow(TorrentFollow $follow, Torrent $torrent): bool
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);
        $metadataTitle = $this->normalizedTitle((string) ($metadata['title'] ?? ''));
        $followTitle = $follow->normalized_title;

        if ($metadataTitle === '' || ! Str::contains($metadataTitle, $followTitle)) {
            return false;
        }

        if ($follow->type !== null && Str::lower((string) ($metadata['type'] ?? '')) !== Str::lower($follow->type)) {
            return false;
        }

        if ($follow->resolution !== null && Str::lower((string) ($metadata['resolution'] ?? '')) !== Str::lower($follow->resolution)) {
            return false;
        }

        if ($follow->source !== null && Str::lower((string) ($metadata['source'] ?? '')) !== Str::lower($follow->source)) {
            return false;
        }

        if ($follow->year !== null && (int) ($metadata['year'] ?? 0) !== (int) $follow->year) {
            return false;
        }

        return true;
    }

    public function normalizedTitle(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', ' ')
            ->squish()
            ->value();
    }
}
