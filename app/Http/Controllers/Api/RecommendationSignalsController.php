<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Recommendations\RecommendationSignalService;
use Illuminate\Http\JsonResponse;

final class RecommendationSignalsController extends Controller
{
    public function __invoke(RecommendationSignalService $signals): JsonResponse
    {
        return response()->json($signals->payload());
    }
}
