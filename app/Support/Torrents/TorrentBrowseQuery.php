<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use Illuminate\Database\Eloquent\Builder;

final class TorrentBrowseQuery
{
    public function apply(Builder $query, TorrentBrowseFilters $filters): Builder
    {
        if ($filters->q !== '') {
            $search = mb_strtolower($filters->q);
            $query->where(function (Builder $inner) use ($search): void {
                $inner->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            });
        }

        if ($filters->type !== '') {
            $query->whereHas('metadata', function (Builder $metadataQuery) use ($filters): void {
                $metadataQuery->where('type', $filters->type);
            });
        }

        if ($filters->resolution !== '') {
            $query->whereHas('metadata', function (Builder $metadataQuery) use ($filters): void {
                $metadataQuery->where('resolution', $filters->resolution);
            });
        }

        if ($filters->source !== '') {
            $query->whereHas('metadata', function (Builder $metadataQuery) use ($filters): void {
                $metadataQuery->where('source', $filters->source);
            });
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
}
