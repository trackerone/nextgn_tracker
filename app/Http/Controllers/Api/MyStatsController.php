<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MyStatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var UserStat $stats */
        $stats = UserStat::query()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        $uploadedBytes = (int) $stats->uploaded_bytes;
        $downloadedBytes = (int) $stats->downloaded_bytes;
        $ratio = $downloadedBytes > 0 ? $uploadedBytes / $downloadedBytes : null;

        return response()->json([
            'uploaded_bytes' => $uploadedBytes,
            'downloaded_bytes' => $downloadedBytes,
            'ratio' => $ratio,
            'ratio_display' => $this->formatRatio($uploadedBytes, $downloadedBytes, $ratio),
            'completed_torrents_count' => (int) $stats->completed_torrents_count,
        ]);
    }

    private function formatRatio(int $uploadedBytes, int $downloadedBytes, ?float $ratio): string
    {
        if ($ratio === null) {
            return $uploadedBytes > 0 && $downloadedBytes === 0 ? '∞' : '—';
        }

        return number_format($ratio, 2, '.', '');
    }
}
