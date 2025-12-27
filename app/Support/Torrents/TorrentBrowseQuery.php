<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class TorrentBrowseQuery
{
    public function apply(Builder $query, TorrentBrowseFilters $filters): Builder
    {
        $model = $query->getModel();
        $table = $model->getTable();

        // 1) Soft deletes (if used)
        // Default Eloquent queries already exclude trashed rows when SoftDeletes is enabled.
        // (Calling withoutTrashed() here confuses static analysis because the Builder is generic.)
    
        // 2) Additional "visibility" constraints if columns exist
        // These are common patterns used in trackers; tests expect rejected/unapproved to be hidden for regular users.
        if (Schema::hasColumn($table, 'is_approved')) {
            $query->where('is_approved', 1);
        }
        if (Schema::hasColumn($table, 'approved')) {
            $query->where('approved', 1);
        }
        if (Schema::hasColumn($table, 'status')) {
            // common: status = 'approved'
            $query->where('status', 'approved');
        }
        if (Schema::hasColumn($table, 'is_rejected')) {
            $query->where('is_rejected', 0);
        }
        if (Schema::hasColumn($table, 'rejected_at')) {
            $query->whereNull('rejected_at');
        }
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($filters->q !== '') {
            $search = mb_strtolower($filters->q);
            $query->where(function (Builder $inner) use ($search): void {
                $inner->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            });
        }

        if ($filters->type !== '') {
            $query->where('type', $filters->type);
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
            'size' => 'size',
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
