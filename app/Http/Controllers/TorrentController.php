<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BrowseTorrentsRequest;
use App\Models\Category;
use App\Models\Torrent;
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

    public function index(BrowseTorrentsRequest $request): Response|JsonResponse
    {
        $filters = $request->filters();
        $query = (new TorrentBrowseQuery)->apply(Torrent::query()->visible(), $filters);

        if ($request->expectsJson()) {
            return response()->json($query->get());
        }

        $perPage = (int) config('torrents.per_page', 25);
        $torrents = $query->paginate($perPage)->appends($filters->queryParams());

        $types = Torrent::query()
            ->select('type')
            ->whereNotNull('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->all();

        $categories = Category::query()
            ->orderBy('name')
            ->get();

        return response()->view('torrents.index', [
            'torrents' => $torrents,
            'types' => $types,
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
        $model = Torrent::query()
            ->where('id', $torrent)
            ->orWhere('slug', $torrent)
            ->firstOrFail();

        $this->authorize('view', $model);

        if ($request->expectsJson()) {
            return response()->json($model);
        }

        $descriptionText = (string) ($model->description ?? '');
        $descriptionHtml = nl2br(e($descriptionText));

        $nfoText = (string) ($model->nfo_text ?? '');
        $nfoHtml = nl2br(e($nfoText));

        return response()->view('torrents.show', [
            'torrent' => $model,
            'descriptionHtml' => $descriptionHtml,
            'nfoText' => $nfoText,
            'nfoHtml' => $nfoHtml,
        ]);
    }
}
