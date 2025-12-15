<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use Illuminate\Database\Eloquent\Builder;

final class TorrentBrowseQuery
{
    /**
     * Apply browse filters in a deterministic, test-friendly way.
     *
     * NOTE: We intentionally avoid JSON functions here to keep SQLite test
     * environments stable. Tag search can be reintroduced behind DB capability.
     */
    public function apply(Builder $query, TorrentBrowseFilters $filters): Builder
    {
        if ($filters->q !== '') {
            $search = mb_strtolower($filters->q);

            $query->where(function (Builder $inner) use ($search): void {
                $inner->whereRaw('LOWER(name) LIKE ?', ['%' . $search . '%']);
            });
        }

        if ($filters->type !== '') {
            $query->where('type', $filters->type);
        }

        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }

        $orderMap = [
            // keep aliases if tests use them
            'id' => 'id',
            'name' => 'name',
            'created' => 'uploaded_at',
            'uploaded' => 'uploaded_at',
            'uploaded_at' => 'uploaded_at',
            'size' => 'size',
            'seeders' => 'seeders',
            'leechers' => 'leechers',
            'completed' => 'completed',
        ];

        $orderColumn = $orderMap[$filters->order] ?? 'uploaded_at';

        // Deterministic ordering: primary + stable tie-breaker
        $query->orderBy($orderColumn, $filters->direction)
            ->orderByDesc('id');

        return $query;
    }
}
