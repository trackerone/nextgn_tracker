<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryMetadataService;
use Illuminate\Http\JsonResponse;

final class DiscoveryMetadataController extends Controller
{
    public function __invoke(DiscoveryMetadataService $metadata): JsonResponse
    {
        return response()->json($metadata->aggregateMany([
            'sources' => 'source',
            'resolutions' => 'resolution',
            'languages' => 'language',
            'audio_languages' => 'audio_language',
            'subtitle_languages' => 'subtitle_language',
            'release_groups' => 'release_group',
        ]));
    }
}
