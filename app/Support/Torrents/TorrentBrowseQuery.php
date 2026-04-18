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
            $this->applyMetadataFilter($query, 'type', $filters->type);
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
