<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TorrentRepositoryInterface;
use Illuminate\Http\JsonResponse;
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

    public function show(string $slug): JsonResponse
    {
        $torrent = $this->torrents->findBySlug($slug);

        if ($torrent === null || ! $torrent->isVisible()) {
            throw new NotFoundHttpException();
        }

        return response()->json($torrent);
    }
}
