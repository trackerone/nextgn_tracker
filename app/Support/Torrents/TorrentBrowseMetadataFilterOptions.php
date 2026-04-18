<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use App\Models\TorrentMetadata;
use Illuminate\Database\Eloquent\Builder;

final class TorrentBrowseMetadataFilterOptions
{
    /**
     * @return array{types: array<int, string>, resolutions: array<int, string>, sources: array<int, string>}
     */
    public function forVisibleBrowse(): array
    {
        return [
            'types' => $this->distinctValues('type'),
            'resolutions' => $this->distinctValues('resolution'),
            'sources' => $this->distinctValues('source'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function distinctValues(string $column): array
    {
        return TorrentMetadata::query()
            ->select($column)
            ->whereHas('torrent', function (Builder $query): void {
                $query->visible();
            })
            ->whereNotNull("torrent_metadata.{$column}")
            ->where("torrent_metadata.{$column}", '!=', '')
            ->distinct()
            ->orderBy("torrent_metadata.{$column}")
            ->pluck($column)
            ->values()
            ->all();
    }
}
