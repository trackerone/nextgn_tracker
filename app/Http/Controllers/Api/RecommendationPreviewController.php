<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use App\Support\Recommendations\RecommendationPreviewFoundationService;
use Illuminate\Http\JsonResponse;

final class RecommendationPreviewController extends Controller
{
    public function __invoke(RecommendationPreviewFoundationService $preview): JsonResponse
    {
        return response()->json([
            'version' => 1,
            'readonly' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'preview_groups' => array_map(
                fn (array $group): array => [
                    'group' => $group['group'],
                    'items' => array_map(
                        fn (array $candidate): array => $this->previewItem($candidate),
                        $group['candidates'],
                    ),
                ],
                $preview->previewGroups(),
            ),
        ]);
    }

    /**
     * @param  array{torrent: Torrent, metadata: array<string, mixed>}  $candidate
     * @return array{torrent: array{id: int, name: string}, metadata: array<string, mixed>, reasons: array<int, array{field: string, value: mixed}>}
     */
    private function previewItem(array $candidate): array
    {
        $torrent = $candidate['torrent'];
        $metadata = $candidate['metadata'];

        return [
            'torrent' => [
                'id' => (int) $torrent->id,
                'name' => (string) $torrent->name,
            ],
            'metadata' => $metadata,
            'reasons' => $this->reasons($metadata),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{field: string, value: mixed}>
     */
    private function reasons(array $metadata): array
    {
        $reasons = [];

        foreach (['source', 'resolution', 'language', 'release_group'] as $field) {
            $value = $metadata[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $reasons[] = [
                'field' => $field,
                'value' => $value,
            ];
        }

        return $reasons;
    }
}
