<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Recommendations\RecommendationEngineService;
use Illuminate\Http\JsonResponse;

final class RecommendationOutputController extends Controller
{
    public function __invoke(RecommendationEngineService $engine): JsonResponse
    {
        $payload = $engine->payload();

        return response()->json([
            'version' => 1,
            'readonly' => true,
            'recommendation_groups' => $payload['recommendation_groups'],
        ]);
    }
}
