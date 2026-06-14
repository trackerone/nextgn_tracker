<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Recommendations\RecommendationEngineService;
use Illuminate\Http\JsonResponse;

final class RecommendationEngineController extends Controller
{
    public function __invoke(RecommendationEngineService $engine): JsonResponse
    {
        $payload = $engine->payload();
        unset($payload['personalized']);

        return response()->json($payload);
    }
}
