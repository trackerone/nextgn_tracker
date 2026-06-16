<?php

declare(strict_types=1);

namespace App\Support\Recommendations;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use Illuminate\Database\Eloquent\Builder;

final class RecommendationTorrentResolver
{
    private const TORRENT_LIMIT_PER_RECOMMENDATION = 3;

    /**
     * @var list<string>
     */
    private const MATCH_FIELDS = [
        'source',
        'resolution',
        'language',
    ];

    public function __construct(private readonly RecommendationEngineService $engine) {}

    /**
     * @return array<int, array{recommendation: array{identifier: string, title: string, explanation: string, metadata: array<string, string>}, torrents: array<int, array{torrent: array{id: int, name: string}, metadata: array<string, mixed>, match_reason: string, matched_fields: array<int, array{field: string, value: mixed}>}>}>
     */
    public function recommendationsWithTorrents(): array
    {
        /** @var array<int, array{source: string, resolution: string, language: string}> $groups */
        $groups = $this->engine->payload()['recommendation_groups'];

        return array_map(
            fn (array $group): array => [
                'recommendation' => $this->recommendation($group),
                'torrents' => $this->matchingTorrents($group),
            ],
            $groups,
        );
    }

    /**
     * @param  array{source: string, resolution: string, language: string}  $group
     * @return array{identifier: string, title: string, explanation: string, metadata: array<string, string>}
     */
    private function recommendation(array $group): array
    {
        $metadata = $this->metadataSummary($group);
        $title = implode(' · ', array_values($metadata));

        return [
            'identifier' => sha1(implode('|', array_values($metadata))),
            'title' => $title,
            'explanation' => 'Metadata recommendation output resolved against visible torrents with matching taxonomy fields.',
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array{source: string, resolution: string, language: string}  $group
     * @return array<string, string>
     */
    private function metadataSummary(array $group): array
    {
        return [
            'source' => $group['source'],
            'resolution' => $group['resolution'],
            'language' => $group['language'],
        ];
    }

    /**
     * @param  array{source: string, resolution: string, language: string}  $group
     * @return array<int, array{torrent: array{id: int, name: string}, metadata: array<string, mixed>, match_reason: string, matched_fields: array<int, array{field: string, value: mixed}>}>
     */
    private function matchingTorrents(array $group): array
    {
        return Torrent::query()
            ->visible()
            ->with('metadata')
            ->whereHas('metadata', function (Builder $query) use ($group): void {
                foreach (self::MATCH_FIELDS as $field) {
                    $query->where($field, $group[$field]);
                }
            })
            ->orderByDesc('seeders')
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->limit(self::TORRENT_LIMIT_PER_RECOMMENDATION)
            ->get()
            ->map(fn (Torrent $torrent): array => $this->torrentMatch($torrent, $group))
            ->all();
    }

    /**
     * @param  array{source: string, resolution: string, language: string}  $group
     * @return array{torrent: array{id: int, name: string}, metadata: array<string, mixed>, match_reason: string, matched_fields: array<int, array{field: string, value: mixed}>}
     */
    private function torrentMatch(Torrent $torrent, array $group): array
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);
        $matchedFields = $this->matchedFields($metadata, $group);

        return [
            'torrent' => [
                'id' => (int) $torrent->id,
                'name' => (string) $torrent->name,
            ],
            'metadata' => $metadata,
            'match_reason' => 'Torrent metadata matches the recommendation output taxonomy for '.implode(', ', array_column($matchedFields, 'field')).'.',
            'matched_fields' => $matchedFields,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{source: string, resolution: string, language: string}  $group
     * @return array<int, array{field: string, value: mixed}>
     */
    private function matchedFields(array $metadata, array $group): array
    {
        $fields = [];

        foreach (self::MATCH_FIELDS as $field) {
            if (($metadata[$field] ?? null) !== $group[$field]) {
                continue;
            }

            $fields[] = [
                'field' => $field,
                'value' => $group[$field],
            ];
        }

        return $fields;
    }
}
