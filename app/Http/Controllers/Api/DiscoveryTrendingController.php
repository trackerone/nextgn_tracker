<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscoveryTrendingRequest;
use App\Support\Discovery\DiscoveryMetadataService;
use Illuminate\Http\JsonResponse;

final class DiscoveryTrendingController extends Controller
{
    private const AGGREGATE_LIMIT = 25;

    /**
     * @var array<string, string>
     */
    private const CATEGORY_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'release_groups' => 'release_group',
    ];

    public function __invoke(DiscoveryTrendingRequest $request): JsonResponse
    {
        $windowDays = $request->windowDays();
        $category = $request->category();
        $categories = $category === null
            ? self::CATEGORY_FIELDS
            : [$category => self::CATEGORY_FIELDS[$category]];

        $payload = [];

        foreach ($categories as $responseKey => $field) {
            $payload[$responseKey] = $this->aggregate($field, $windowDays);
        }

        return response()->json($payload);
    }

    /**
     * @var array<string, string>
     */
    private const CATEGORY_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'release_groups' => 'release_group',
    ];

    public function __invoke(DiscoveryTrendingRequest $request, DiscoveryMetadataService $metadata): JsonResponse
    {
        $windowDays = $request->windowDays();
        $category = $request->category();
        $categories = $category === null
            ? self::CATEGORY_FIELDS
            : [$category => self::CATEGORY_FIELDS[$category]];

        return response()->json($metadata->aggregateMany($categories, $windowDays));
    }
}
