<?php

declare(strict_types=1);

namespace App\Support\Recommendations;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class RecommendationPreviewFoundationService
{
    private const PREVIEW_LIMIT_PER_GROUP = 3;

    public function __construct(private readonly RecommendationEngineService $engine) {}

    /**
     * @return array<int, array{group: array{source: string, resolution: string, language: string}, candidates: array<int, array{torrent: Torrent, metadata: array<string, mixed>}>}>
     */
    public function previewGroups(): array
    {
        $payload = $this->engine->payload();
        $groups = $payload['recommendation_groups'];
        $previews = [];

        foreach ($groups as $group) {
            $candidates = $this->visibleCandidatesForGroup($group)
                ->map(fn (Torrent $torrent): array => [
                    'torrent' => $torrent,
                    'metadata' => TorrentMetadataView::forTorrent($torrent),
                ])
                ->all();

            $previews[] = [
                'group' => $group,
                'candidates' => $candidates,
            ];
        }

        return $previews;
    }

    /**
     * @param  array{source: string, resolution: string, language: string}  $group
     * @return Collection<int, Torrent>
     */
    private function visibleCandidatesForGroup(array $group): Collection
    {
        return Torrent::query()
            ->visible()
            ->with('metadata')
            ->whereHas('metadata', function (Builder $query) use ($group): void {
                $query
                    ->where('source', $group['source'])
                    ->where('resolution', $group['resolution'])
                    ->where('language', $group['language']);
            })
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT_PER_GROUP)
            ->get();
    }
}
