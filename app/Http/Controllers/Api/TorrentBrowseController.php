<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseTorrentsRequest;
use App\Http\Resources\TorrentBrowseResource;
use App\Models\Torrent;
use App\Services\Torrents\ReleaseFamilyBestVersionResolver;
use App\Services\Torrents\ReleaseUpgradeAdvisor;
use App\Support\Torrents\TorrentBrowseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

final class TorrentBrowseController extends Controller
{
    public function index(
        BrowseTorrentsRequest $request,
        TorrentBrowseQuery $browseQuery,
        ReleaseFamilyBestVersionResolver $bestVersionResolver,
        ReleaseUpgradeAdvisor $upgradeAdvisor
    ): JsonResponse {
        $query = Torrent::query()
            ->visible()
            ->with(['category', 'uploader', 'metadata']);

        $filters = $request->filters();

        $query = $browseQuery->apply($query, $filters);

        $perPage = $request->perPage(25);

        /** @var LengthAwarePaginator<int, Torrent> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        $collection = $paginator->getCollection();
        $releaseFamilyData = $bestVersionResolver->resolve($collection);
        $upgradeData = $upgradeAdvisor->advise($request->user(), $collection, $releaseFamilyData);

        $collection->each(function (Torrent $torrent) use ($releaseFamilyData, $upgradeData): void {
            $torrent->setAttribute('release_family_intelligence', [
                'key' => $releaseFamilyData[(int) $torrent->id]['family_key'] ?? sprintf('torrent:%d', (int) $torrent->id),
                'quality_score' => $releaseFamilyData[(int) $torrent->id]['quality_score'] ?? 0,
                'is_best_version' => $releaseFamilyData[(int) $torrent->id]['is_best_version'] ?? true,
                'best_torrent_id' => $releaseFamilyData[(int) $torrent->id]['best_torrent_id'] ?? (int) $torrent->id,
                'upgrade_available' => $upgradeData[(int) $torrent->id]['upgrade_available'] ?? false,
                'upgrade_from_torrent_id' => $upgradeData[(int) $torrent->id]['upgrade_from_torrent_id'] ?? null,
            ]);
        });

        return response()->json([
            'data' => TorrentBrowseResource::collection($collection)->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
