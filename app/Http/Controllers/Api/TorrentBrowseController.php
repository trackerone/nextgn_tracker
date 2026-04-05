<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use App\Support\Torrents\TorrentBrowseFilters;
use App\Support\Torrents\TorrentBrowseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class TorrentBrowseController extends Controller
{
    public function index(Request $request, TorrentBrowseQuery $browseQuery): JsonResponse
    {
        $query = Torrent::query()
            ->visible()
            ->with(['category', 'uploader']);

        $filters = TorrentBrowseFilters::fromRequest($request);

        $query = $browseQuery->apply($query, $filters);

        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        /** @var LengthAwarePaginator<int, Torrent> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        /** @var Collection<int, Torrent> $items */
        $items = $paginator->getCollection();

        $data = $items->map(static function ($torrent): array {
            /** @var Torrent $torrent */
            return [
                'id' => $torrent->id,
                'slug' => $torrent->slug,
                'name' => $torrent->name,
                'category' => $torrent->category
                    ? [
                        'id' => $torrent->category->id,
                        'name' => $torrent->category->name,
                        'slug' => $torrent->category->slug,
                    ]
                    : null,
                'type' => $torrent->type,
                'size_bytes' => (int) ($torrent->size_bytes ?? 0),
                'size_human' => $torrent->formatted_size,
                'seeders' => (int) ($torrent->seeders ?? 0),
                'leechers' => (int) ($torrent->leechers ?? 0),
                'completed' => (int) ($torrent->completed ?? 0),
                'freeleech' => (bool) ($torrent->freeleech ?? false),
                'uploaded_at' => $torrent->uploadedAtForDisplay()?->toISOString(),
                'uploaded_at_human' => $torrent->uploadedAtForDisplay()?->diffForHumans(),
                'uploader' => $torrent->uploader
                    ? [
                        'id' => $torrent->uploader->id,
                        'name' => $torrent->uploader->name,
                    ]
                    : null,
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
