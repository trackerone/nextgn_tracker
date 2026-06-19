<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryHealthService;
use App\Support\Discovery\DiscoveryOperationsActionHintService;
use App\Support\Discovery\DiscoveryOperationsDrilldownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DiscoveryOperationsActionHintController extends Controller
{
    public function __invoke(Request $request, DiscoveryOperationsActionHintService $actionHints): JsonResponse
    {
        $filters = $request->validate([
            'field' => ['nullable', 'string', Rule::in(array_keys(DiscoveryHealthService::CORE_METADATA_FIELDS))],
            'status' => ['nullable', 'string', Rule::in(DiscoveryOperationsDrilldownService::DISCOVERY_STATUSES)],
            'priority' => ['nullable', 'string', Rule::in(DiscoveryOperationsActionHintService::PRIORITIES)],
        ]);

        return response()->json($actionHints->payload($filters), options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
