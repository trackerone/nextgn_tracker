<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Recommendations\RecommendationExplainabilityService;
use Illuminate\Http\JsonResponse;

final class RecommendationExplainabilityController extends Controller
{
    public function __invoke(RecommendationExplainabilityService $explainability): JsonResponse
    {
        return response()->json($explainability->payload(), options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
