<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use Illuminate\Database\Eloquent\Builder;

final class TorrentBrowseQuery
{
    public function apply(Builder $query, TorrentBrowseFilters $filters): Builder
    {
        if ($filters->q !== '') {
            $searchExpression = TorrentSearchExpression::fromQuery($filters->q);

            $searchText = $searchExpression->hasMetadataDirectives()
                ? $searchExpression->text
                : $filters->q;

            if ($searchText !== '') {
                $search = mb_strtolower($searchText);
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
                });
            }

            $this->applySearchMetadataDirectives($query, $searchExpression);
        }

        if ($filters->type !== '') {
            $this->applyMetadataFilter($query, 'type', $filters->type);
        }

        if ($filters->releaseGroup !== '') {
            $this->applyMetadataWhereHas($query, 'release_group', $filters->releaseGroup);
        }

        if ($filters->language !== '') {
            $this->applyMetadataWhereHas($query, 'language', $filters->language);
        }

        if ($filters->audioLanguage !== '') {
            $this->applyMetadataWhereHas($query, 'audio_language', $filters->audioLanguage);
        }

        if ($filters->subtitleLanguage !== '') {
            $this->applyMetadataWhereHas($query, 'subtitle_language', $filters->subtitleLanguage);
        }

        if ($filters->resolution !== '') {
            $this->applyMetadataFilter($query, 'resolution', $filters->resolution);
        }

        if ($filters->source !== '') {
            $this->applyMetadataFilter($query, 'source', $filters->source);
        }

        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }

        $orderMap = [
            'id' => 'id',
            'name' => 'name',
            'created' => 'uploaded_at',
            'uploaded' => 'uploaded_at',
            'uploaded_at' => 'uploaded_at',
            'size' => 'size_bytes',
            'size_bytes' => 'size_bytes',
            'seeders' => 'seeders',
            'leechers' => 'leechers',
            'completed' => 'completed',
        ];

        $orderColumn = $orderMap[$filters->order] ?? 'uploaded_at';

        return $query
            ->orderBy($orderColumn, $filters->direction)
            ->orderByDesc('id');
    }

    private function applySearchMetadataDirectives(Builder $query, TorrentSearchExpression $searchExpression): void
    {
        if ($searchExpression->releaseGroup !== null) {
            $this->applyMetadataWhereHas($query, 'release_group', $searchExpression->releaseGroup);
        }

        if ($searchExpression->source !== null) {
            $this->applyMetadataFilter($query, 'source', $searchExpression->source);
        }

        if ($searchExpression->resolution !== null) {
            $this->applyMetadataFilter($query, 'resolution', $searchExpression->resolution);
        }

        if ($searchExpression->language !== null) {
            $this->applyMetadataWhereHas($query, 'language', $searchExpression->language);
        }

        if ($searchExpression->audioLanguage !== null) {
            $this->applyMetadataWhereHas($query, 'audio_language', $searchExpression->audioLanguage);
        }

        if ($searchExpression->subtitleLanguage !== null) {
            $this->applyMetadataWhereHas($query, 'subtitle_language', $searchExpression->subtitleLanguage);
        }

        if ($searchExpression->year !== null) {
            $query->whereHas('metadata', function (Builder $metadataQuery) use ($searchExpression): void {
                $metadataQuery->where('year', $searchExpression->year);
            });
        }
    }

    private function applyMetadataWhereHas(Builder $query, string $column, string $value): void
    {
        $query->whereHas('metadata', function (Builder $metadataQuery) use ($column, $value): void {
            $metadataQuery->where($column, $value);
        });
    }

    private function applyMetadataFilter(Builder $query, string $column, string $value): void
    {
        $query->where(function (Builder $innerQuery) use ($column, $value): void {
            $innerQuery
                ->whereHas('metadata', function (Builder $metadataQuery) use ($column, $value): void {
                    $metadataQuery->where($column, $value);
                })
                ->orWhere(function (Builder $fallbackQuery) use ($column, $value): void {
                    $fallbackQuery
                        ->whereDoesntHave('metadata')
                        ->where("torrents.{$column}", $value);
                });
        });
    }
}
