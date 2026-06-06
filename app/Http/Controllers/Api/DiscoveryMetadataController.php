<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TorrentMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

final class DiscoveryMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'sources' => $this->aggregate('source'),
            'resolutions' => $this->aggregate('resolution'),
            'languages' => $this->aggregate('language'),
            'audio_languages' => $this->aggregate('audio_language'),
            'subtitle_languages' => $this->aggregate('subtitle_language'),
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
            ->whereHas('torrent', static function (Builder $query): void {
                $query->visible();
            })
            ->groupBy($field)
            ->orderByDesc('count')
            ->orderBy($field)
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
