<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryOperationsPriorityService;
use Illuminate\Http\JsonResponse;

final class DiscoveryOperationsPriorityController extends Controller
{
    public function __invoke(DiscoveryOperationsPriorityService $priorities): JsonResponse
    {
        return response()->json($priorities->payload(), options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
