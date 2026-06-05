<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Support\Torrents\TorrentMetadataPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DiscoveryDashboardBuilder
{
    private const SECTION_LIMIT = 6;

    /**
     * @return array{
     *     discoverySections: array<int, array{key: string, title: string, summary: string, empty: string, torrents: Collection<int, array{torrent: Torrent, title: string, badges: array<int, string>, meta: array<int, array{label: string, value: string}>}>}>,
     *     metadataCategories: Collection<int, array{type: string, count: int}>
     * }
     */
    public function build(): array
    {
        return [
            'discoverySections' => [
                [
                    'key' => 'latest',
                    'title' => 'Latest uploads',
                    'summary' => 'Fresh visible torrents with normalized release metadata.',
                    'empty' => 'No latest uploads are visible yet.',
                    'torrents' => $this->present($this->latestUploads()),
                ],
                [
                    'key' => 'popular',
                    'title' => 'Popular releases',
                    'summary' => 'Visible releases with the strongest current swarm and snatch activity.',
                    'empty' => 'No popular releases are visible yet.',
                    'torrents' => $this->present($this->popularReleases()),
                ],
                [
                    'key' => 'danish',
                    'title' => 'Danish releases',
                    'summary' => 'Releases tagged with Danish language, audio, or subtitle metadata.',
                    'empty' => 'No Danish releases are visible yet.',
                    'torrents' => $this->present($this->localizedReleases(['da', 'danish', 'dansk'])),
                ],
                [
                    'key' => 'nordic',
                    'title' => 'Nordic releases',
                    'summary' => 'Releases carrying Danish, Swedish, Norwegian, Finnish, Icelandic, or Nordic metadata.',
                    'empty' => 'No Nordic releases are visible yet.',
                    'torrents' => $this->present($this->localizedReleases([
                        'da',
                        'danish',
                        'dansk',
                        'sv',
                        'swedish',
                        'svenska',
                        'no',
                        'norwegian',
                        'norsk',
                        'fi',
                        'finnish',
                        'suomi',
                        'is',
                        'icelandic',
                        'islenska',
                        'nordic',
                    ])),
                ],
                [
                    'key' => 'subtitles',
                    'title' => 'Releases with subtitles',
                    'summary' => 'Metadata-rich releases with subtitle language or subtitle list data.',
                    'empty' => 'No releases with subtitle metadata are visible yet.',
                    'torrents' => $this->present($this->subtitleReleases()),
                ],
            ],
            'metadataCategories' => $this->metadataCategories(),
        ];
    }

    /**
     * @return Collection<int, Torrent>
     */
    private function latestUploads(): Collection
    {
        /** @var Collection<int, Torrent> $torrents */
        $torrents = $this->baseQuery()
            ->orderByDesc('uploaded_at')
            ->orderByDesc('created_at')
            ->limit(self::SECTION_LIMIT)
            ->get();

        return $torrents;
    }

    /**
     * @return Collection<int, Torrent>
     */
    private function popularReleases(): Collection
    {
        /** @var Collection<int, Torrent> $torrents */
        $torrents = $this->baseQuery()
            ->orderByDesc('seeders')
            ->orderByDesc('completed')
            ->orderByDesc('created_at')
            ->limit(self::SECTION_LIMIT)
            ->get();

        return $torrents;
    }

    /**
     * @param  array<int, string>  $tokens
     * @return Collection<int, Torrent>
     */
    private function localizedReleases(array $tokens): Collection
    {
        /** @var Collection<int, Torrent> $torrents */
        $torrents = $this->baseQuery()
            ->whereHas('metadata', function (Builder $query) use ($tokens): void {
                $this->whereMetadataContainsAny($query, [
                    'language',
                    'audio_language',
                    'subtitle_language',
                    'subtitles',
                ], $tokens);
            })
            ->orderByDesc('uploaded_at')
            ->orderByDesc('created_at')
            ->limit(self::SECTION_LIMIT)
            ->get();

        return $torrents;
    }

    /**
     * @return Collection<int, Torrent>
     */
    private function subtitleReleases(): Collection
    {
        /** @var Collection<int, Torrent> $torrents */
        $torrents = $this->baseQuery()
            ->whereHas('metadata', function (Builder $query): void {
                $query
                    ->whereNotNull('subtitle_language')
                    ->where('subtitle_language', '!=', '')
                    ->orWhere(function (Builder $nested): void {
                        $nested
                            ->whereNotNull('subtitles')
                            ->where('subtitles', '!=', '');
                    });
            })
            ->orderByDesc('uploaded_at')
            ->orderByDesc('created_at')
            ->limit(self::SECTION_LIMIT)
            ->get();

        return $torrents;
    }

    /**
     * @return Builder<Torrent>
     */
    private function baseQuery(): Builder
    {
        return Torrent::query()
            ->visible()
            ->with(['metadata:id,torrent_id,title,year,type,resolution,source,language,audio_language,subtitle_language,subtitles'])
            ->select([
                'id',
                'user_id',
                'category_id',
                'name',
                'slug',
                'size_bytes',
                'file_count',
                'seeders',
                'leechers',
                'completed',
                'is_approved',
                'is_banned',
                'status',
                'uploaded_at',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * @param  Builder<TorrentMetadata>  $query
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $tokens
     */
    private function whereMetadataContainsAny(Builder $query, array $columns, array $tokens): void
    {
        $query->where(function (Builder $outer) use ($columns, $tokens): void {
            foreach ($columns as $column) {
                foreach ($tokens as $token) {
                    $outer->orWhereRaw(sprintf('LOWER(%s) LIKE ?', $column), ['%'.Str::lower($token).'%']);
                }
            }
        });
    }

    /**
     * @param  Collection<int, Torrent>  $torrents
     * @return Collection<int, array{torrent: Torrent, title: string, badges: array<int, string>, meta: array<int, array{label: string, value: string}>}>
     */
    private function present(Collection $torrents): Collection
    {
        return $torrents->map(function (Torrent $torrent): array {
            $metadata = $this->metadataArray($torrent);
            $title = trim((string) ($metadata['title'] ?? '')) ?: $torrent->name;
            /** @var array<int, array{label: string, value: string}> $meta */
            $meta = [
                ['label' => 'Seeders', 'value' => number_format((int) $torrent->seeders)],
                ['label' => 'Completed', 'value' => number_format((int) $torrent->completed)],
                ['label' => 'Uploaded', 'value' => $torrent->uploadedAtForDisplay()?->format('Y-m-d') ?? 'recently'],
            ];

            return [
                'torrent' => $torrent,
                'title' => $title,
                'badges' => $this->metadataBadges($metadata),
                'meta' => $meta,
            ];
        });
    }

    /**
     * @return array<string, int|string|null>
     */
    private function metadataArray(Torrent $torrent): array
    {
        $metadata = $torrent->getRelation('metadata');

        if (! $metadata instanceof TorrentMetadata) {
            return [];
        }

        return [
            'title' => $metadata->title,
            'year' => $metadata->year,
            'type' => $metadata->type,
            'resolution' => $metadata->resolution,
            'source' => $metadata->source,
            'language' => $metadata->language,
            'audio_language' => $metadata->audio_language,
            'subtitle_language' => $metadata->subtitle_language,
            'subtitles' => $metadata->subtitles,
        ];
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     * @return array<int, string>
     */
    private function metadataBadges(array $metadata): array
    {
        $badges = TorrentMetadataPresenter::listingBadges($metadata);

        foreach ([
            'Language' => 'language',
            'Audio' => 'audio_language',
            'Subtitle language' => 'subtitle_language',
            'Subtitles' => 'subtitles',
        ] as $label => $key) {
            $value = $this->formattedText($metadata[$key] ?? null);

            if ($value !== null) {
                $badges[] = $label.': '.$value;
            }
        }

        return array_values(array_unique($badges));
    }

    private function formattedText(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtoupper(trim($value));
    }

    /**
     * @return Collection<int, array{type: string, count: int}>
     */
    private function metadataCategories(): Collection
    {
        /** @var Collection<int, Torrent> $rows */
        $rows = Torrent::query()
            ->visible()
            ->join('torrent_metadata', 'torrent_metadata.torrent_id', '=', 'torrents.id')
            ->whereNotNull('torrent_metadata.type')
            ->where('torrent_metadata.type', '!=', '')
            ->selectRaw('torrent_metadata.type as type, COUNT(*) as total, MAX(torrents.created_at) as latest_activity')
            ->groupBy('torrent_metadata.type')
            ->orderByDesc('latest_activity')
            ->limit(6)
            ->get();

        return $rows->map(fn (Torrent $row): array => [
            'type' => ucfirst(Str::lower((string) $row->getAttribute('type'))),
            'count' => (int) $row->getAttribute('total'),
        ]);
    }
}
