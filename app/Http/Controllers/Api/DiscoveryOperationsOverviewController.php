<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryOperationsOverviewService;
use Illuminate\Http\JsonResponse;

final class DiscoveryOperationsOverviewController extends Controller
{
    public function __invoke(DiscoveryOperationsOverviewService $overview): JsonResponse
    {
        return response()->json($overview->payload(), options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
