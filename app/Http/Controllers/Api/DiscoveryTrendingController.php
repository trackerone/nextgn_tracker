<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use Illuminate\Http\JsonResponse;

final class DiscoveryTrendingController extends Controller
{
    private const AGGREGATE_LIMIT = 25;

    private const RECENT_WINDOW_DAYS = 30;

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'sources' => $this->aggregate('source'),
            'resolutions' => $this->aggregate('resolution'),
            'release_groups' => $this->aggregate('release_group'),
        ]);
    }

    /**
     * @return array<int, array{value: string, count: int}>
     */
    private function aggregate(string $field): array
    {
        $rows = TorrentMetadata::query()
            ->selectRaw(sprintf('%s as value, COUNT(*) as count', $field))
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->whereIn('torrent_id', Torrent::query()
                ->visible()
                ->where('uploaded_at', '>=', now()->subDays(self::RECENT_WINDOW_DAYS))
                ->select('id'))
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
