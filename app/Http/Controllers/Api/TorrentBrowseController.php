<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TorrentBrowseRequest;
use App\Models\Torrent;
use App\Support\Torrents\TorrentBrowseFilters;
use App\Support\Torrents\TorrentBrowseQuery;
use Illuminate\Http\JsonResponse;

final class TorrentBrowseController extends Controller
{
    public function index(TorrentBrowseRequest $request): JsonResponse
    {
        $filters = TorrentBrowseFilters::fromRequest($request);
        $perPage = (int) ($request->integer('per_page') ?: config('torrents.per_page', 25));

        $paginator = (new TorrentBrowseQuery)
            ->apply(Torrent::query()->visible()->with(['category', 'uploader']), $filters)
            ->paginate($perPage)
            ->withQueryString();

        $data = $paginator->getCollection()->map(static function (Torrent $torrent): array {
            return [
                'id' => $torrent->id,
                'slug' => $torrent->slug,
                'name' => $torrent->name,
                'category' => $torrent->category === null ? null : [
                    'id' => $torrent->category->id,
                    'name' => $torrent->category->name,
                    'slug' => $torrent->category->slug,
                ],
                'type' => $torrent->type,
                'size_bytes' => (int) $torrent->size_bytes,
                'size_human' => $torrent->formatted_size,
                'seeders' => (int) $torrent->seeders,
                'leechers' => (int) $torrent->leechers,
                'completed' => (int) $torrent->completed,
                'freeleech' => (bool) $torrent->freeleech,
                'uploaded_at' => $torrent->uploadedAtForDisplay()?->toISOString(),
                'uploaded_at_human' => $torrent->uploadedAtForDisplay()?->diffForHumans(),
                'uploader' => $torrent->uploader === null ? null : [
                    'id' => $torrent->uploader->id,
                    'name' => $torrent->uploader->name,
                ],
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
