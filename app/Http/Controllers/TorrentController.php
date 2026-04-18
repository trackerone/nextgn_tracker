<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Torrents\ResolveTorrentAccessAction;
use App\Http\Requests\BrowseTorrentsRequest;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Category;
use App\Models\Torrent;
use App\Support\Torrents\TorrentBrowseMetadataFilterOptions;
use App\Support\Torrents\TorrentBrowseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TorrentController extends Controller
{
    public function __construct()
    {
        // Matcher tests:
        // - guests -> /login på både index og show
        $this->middleware('auth');
    }

    public function index(
        BrowseTorrentsRequest $request,
        TorrentBrowseQuery $browseQuery,
        TorrentBrowseMetadataFilterOptions $metadataFilterOptions
    ): Response|JsonResponse {
        $filters = $request->filters();
        $query = $browseQuery->apply(
            Torrent::query()->visible()->with('metadata'),
            $filters
        );

        if ($request->expectsJson()) {
            return response()->json($query->get());
        }

        $perPage = (int) config('torrents.per_page', 25);
        $torrents = $query->paginate($perPage)->appends($filters->queryParams());
        $torrentMetadata = TorrentMetadataView::mapByTorrentId($torrents->getCollection());

        $metadataFilterValues = $metadataFilterOptions->forVisibleBrowse();

        $categories = Category::query()
            ->orderBy('name')
            ->get();

        return response()->view('torrents.index', [
            'torrents' => $torrents,
            'torrentMetadata' => $torrentMetadata,
            'types' => $metadataFilterValues['types'],
            'resolutions' => $metadataFilterValues['resolutions'],
            'sources' => $metadataFilterValues['sources'],
            'categories' => $categories,

            // View-friendly (og test-neutralt)
            'filters' => $filters->toArray(),
            'q' => $filters->q,
            'type' => $filters->type,
            'order' => $filters->order !== '' ? $filters->order : 'uploaded_at',
            'direction' => $filters->direction,
        ]);
    }

    public function show(Request $request, string $torrent): Response|JsonResponse
    {
        $model = app(ResolveTorrentAccessAction::class)->execute(
            $torrent,
            'view',
            ['category', 'uploader', 'moderator', 'metadata']
        );

        if ($request->expectsJson()) {
            return response()->json($model);
        }

        $metadata = TorrentMetadataView::forTorrent($model);
        $descriptionText = (string) ($model->description ?? '');
        $descriptionHtml = nl2br(e($descriptionText));

        $nfoText = (string) ($metadata['nfo'] ?? '');
        $nfoHtml = nl2br(e($nfoText));

        return response()->view('torrents.show', [
            'torrent' => $model,
            'metadata' => $metadata,
            'descriptionHtml' => $descriptionHtml,
            'nfoText' => $nfoText,
            'nfoHtml' => $nfoHtml,
        ]);
    }
}
