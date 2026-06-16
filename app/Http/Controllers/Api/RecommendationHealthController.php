<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Recommendations\RecommendationHealthService;
use Illuminate\Http\JsonResponse;

final class RecommendationHealthController extends Controller
{
    public function __invoke(RecommendationHealthService $health): JsonResponse
    {
        return response()->json($health->payload());
    }
}
