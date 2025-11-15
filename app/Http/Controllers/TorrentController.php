<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TorrentRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TorrentController extends Controller
{
    public function __construct(private readonly TorrentRepositoryInterface $torrents)
    {
    }

    public function index(): JsonResponse
    {
        $paginator = $this->torrents->paginateVisible();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse|View
    {
        $torrent = $this->torrents->findBySlug($slug);

        if ($torrent === null || ! $torrent->isVisible() || $torrent->isBanned() || ! $torrent->isApproved()) {
            throw new NotFoundHttpException();
        }

        if ($request->wantsJson()) {
            return response()->json($torrent);
        }

        return view('torrents.show', [
            'torrent' => $torrent,
        ]);
    }
}
