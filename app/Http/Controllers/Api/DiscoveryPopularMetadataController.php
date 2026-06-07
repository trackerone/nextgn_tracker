<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryMetadataService;
use Illuminate\Http\JsonResponse;

final class DiscoveryPopularMetadataController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const CATEGORY_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'release_groups' => 'release_group',
    ];

    public function __invoke(DiscoveryMetadataService $metadata): JsonResponse
    {
        return response()->json($metadata->aggregateMany(self::CATEGORY_FIELDS));
    }
}
