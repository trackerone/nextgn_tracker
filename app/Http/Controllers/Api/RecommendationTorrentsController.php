<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Recommendations\RecommendationTorrentResolver;
use Illuminate\Http\JsonResponse;

final class RecommendationTorrentsController extends Controller
{
    public function __invoke(RecommendationTorrentResolver $resolver): JsonResponse
    {
        return response()->json([
            'version' => 1,
            'readonly' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'pipeline' => ['signals', 'candidates', 'output', 'preview', 'torrents'],
            'recommendations' => $resolver->recommendationsWithTorrents(),
        ]);
    }
}
