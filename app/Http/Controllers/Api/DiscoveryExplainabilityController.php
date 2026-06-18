<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryExplainabilityService;
use Illuminate\Http\JsonResponse;

final class DiscoveryExplainabilityController extends Controller
{
    public function __invoke(DiscoveryExplainabilityService $explainability): JsonResponse
    {
        return response()->json($explainability->payload(), options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
