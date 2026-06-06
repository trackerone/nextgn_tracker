<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscoveryTrendingRequest;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use Illuminate\Http\JsonResponse;

final class DiscoveryTrendingController extends Controller
{
    private const AGGREGATE_LIMIT = 25;

    /**
     * @var array<string, string>
     */
    private const CATEGORY_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'release_groups' => 'release_group',
    ];

    public function __invoke(DiscoveryTrendingRequest $request): JsonResponse
    {
        $windowDays = $request->windowDays();
        $category = $request->category();
        $categories = $category === null
            ? self::CATEGORY_FIELDS
            : [$category => self::CATEGORY_FIELDS[$category]];

        $payload = [];

        foreach ($categories as $responseKey => $field) {
            $payload[$responseKey] = $this->aggregate($field, $windowDays);
        }

        return response()->json($payload);
    }

    /**
     * @return array<int, array{value: string, count: int}>
     */
    private function aggregate(string $field, int $windowDays): array
    {
        $rows = TorrentMetadata::query()
            ->selectRaw(sprintf('%s as value, COUNT(*) as count', $field))
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->whereIn('torrent_id', Torrent::query()
                ->visible()
                ->where('uploaded_at', '>=', now()->subDays($windowDays))
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
