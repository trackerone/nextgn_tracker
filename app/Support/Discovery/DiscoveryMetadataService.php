<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Models\Torrent;
use App\Models\TorrentMetadata;

final class DiscoveryMetadataService
{
    private const AGGREGATE_LIMIT = 25;

    /**
     * @param  array<string, string>  $fields
     * @return array<string, array<int, array{value: string, count: int}>>
     */
    public function aggregateMany(array $fields, ?int $windowDays = null): array
    {
        $aggregates = [];

        foreach ($fields as $responseKey => $field) {
            $aggregates[$responseKey] = $this->aggregate($field, $windowDays);
        }

        return $aggregates;
    }

    /**
     * @return array<int, array{value: string, count: int}>
     */
    public function aggregate(string $field, ?int $windowDays = null): array
    {
        $visibleTorrentIds = Torrent::query()->visible();

        if ($windowDays !== null) {
            $visibleTorrentIds->where('uploaded_at', '>=', now()->subDays($windowDays));
        }

        $rows = TorrentMetadata::query()
            ->selectRaw(sprintf('%s as value, COUNT(*) as count', $field))
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->whereIn('torrent_id', $visibleTorrentIds->select('id'))
            ->groupBy($field)
            ->orderByDesc('count')
            ->orderBy($field)
            ->limit(self::AGGREGATE_LIMIT)
            ->get();

        $aggregates = [];

        foreach ($rows as $row) {
            $aggregates[] = [
                'value' => (string) $row->getAttribute('value'),
                'count' => (int) $row->getAttribute('count'),
            ];
        }

        return $aggregates;
    }
}
