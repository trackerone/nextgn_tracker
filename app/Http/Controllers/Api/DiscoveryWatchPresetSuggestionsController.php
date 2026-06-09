<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscoveryWatchPresetSuggestionsRequest;
use App\Support\Discovery\DiscoveryMetadataService;
use Illuminate\Http\JsonResponse;

final class DiscoveryWatchPresetSuggestionsController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const CATEGORY_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'languages' => 'language',
        'release_groups' => 'release_group',
    ];

    public function __invoke(DiscoveryWatchPresetSuggestionsRequest $request, DiscoveryMetadataService $metadata): JsonResponse
    {
        $category = $request->category();
        $categories = $category === null
            ? self::CATEGORY_FIELDS
            : [$category => self::CATEGORY_FIELDS[$category]];

        return response()->json($metadata->aggregateMany($categories));
    }
}
