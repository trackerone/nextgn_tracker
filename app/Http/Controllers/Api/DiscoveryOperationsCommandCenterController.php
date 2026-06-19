<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryHealthService;
use App\Support\Discovery\DiscoveryOperationsActionHintService;
use App\Support\Discovery\DiscoveryOperationsCommandCenterService;
use App\Support\Discovery\DiscoveryOperationsDrilldownService;
use App\Support\Discovery\DiscoveryOperationsReviewQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DiscoveryOperationsCommandCenterController extends Controller
{
    public function __invoke(Request $request, DiscoveryOperationsCommandCenterService $commandCenter): JsonResponse
    {
        $filters = $request->validate([
            'field' => ['nullable', 'string', Rule::in(array_keys(DiscoveryHealthService::CORE_METADATA_FIELDS))],
            'status' => ['nullable', 'string', Rule::in(DiscoveryOperationsDrilldownService::DISCOVERY_STATUSES)],
            'priority' => ['nullable', 'string', Rule::in(DiscoveryOperationsActionHintService::PRIORITIES)],
            'severity' => ['nullable', 'string', Rule::in(DiscoveryOperationsReviewQueueService::SEVERITIES)],
        ]);

        return response()->json($commandCenter->payload($filters), options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
