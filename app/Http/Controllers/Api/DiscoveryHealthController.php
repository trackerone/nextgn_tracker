<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryHealthService;
use Illuminate\Http\JsonResponse;

final class DiscoveryHealthController extends Controller
{
    public function __invoke(DiscoveryHealthService $health): JsonResponse
    {
        return response()->json($health->payload(), options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
