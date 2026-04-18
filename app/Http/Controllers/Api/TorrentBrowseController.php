<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseTorrentsRequest;
use App\Http\Resources\TorrentBrowseResource;
use App\Models\Torrent;
use App\Support\Torrents\TorrentBrowseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

final class TorrentBrowseController extends Controller
{
    public function index(BrowseTorrentsRequest $request, TorrentBrowseQuery $browseQuery): JsonResponse
    {
        $query = Torrent::query()
            ->visible()
            ->with(['category', 'uploader', 'metadata']);

        $filters = $request->filters();

        $query = $browseQuery->apply($query, $filters);

        $perPage = $request->perPage(25);

        /** @var LengthAwarePaginator<int, Torrent> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => TorrentBrowseResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
