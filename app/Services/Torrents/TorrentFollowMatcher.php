<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Models\TorrentFollow;
use App\Models\TorrentMetadata;
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
            $matches[$follow->getKey()] = $matched;
        }

        return $matches;
    }

    public function matchesFollow(TorrentFollow $follow, Torrent $torrent): bool
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);
        $hasMetadata = $torrent->getRelationValue('metadata') instanceof TorrentMetadata;

        if (! $this->matchesTitle($follow, $torrent, $metadata)) {
            return false;
        }

        if (
            $follow->type !== null
            && (! $hasMetadata || Str::lower((string) ($metadata['type'] ?? '')) !== Str::lower($follow->type))
        ) {
            return false;
        }

        if (
            $follow->resolution !== null
            && (! $hasMetadata || Str::lower((string) ($metadata['resolution'] ?? '')) !== Str::lower($follow->resolution))
        ) {
            return false;
        }

        if (
            $follow->source !== null
            && (! $hasMetadata || Str::lower((string) ($metadata['source'] ?? '')) !== Str::lower($follow->source))
        ) {
            return false;
        }

        if ($follow->year !== null && (! $hasMetadata || (int) ($metadata['year'] ?? 0) !== (int) $follow->year)) {
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

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function matchesTitle(TorrentFollow $follow, Torrent $torrent, array $metadata): bool
    {
        $followTitle = $this->normalizedTitle((string) ($follow->normalized_title ?: $follow->title));
        $metadataTitle = $this->normalizedTitle((string) ($metadata['title'] ?? ''));
        $fallbackTorrentTitle = $this->normalizedTitle($torrent->name);

        $candidateTitle = $metadataTitle !== '' ? $metadataTitle : $fallbackTorrentTitle;

        if ($followTitle === '' || $candidateTitle === '') {
            return false;
        }

        return Str::contains($candidateTitle, $followTitle);
    }
}
