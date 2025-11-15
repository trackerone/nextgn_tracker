<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Torrent;
use App\Services\Security\SanitizationService;
use App\Services\TorrentDownloadService;
use App\Services\UserTorrentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;

class TorrentDownloadController extends Controller
{
    public function __construct(
        private readonly TorrentDownloadService $downloadService,
        private readonly UserTorrentService $userTorrentService,
        private readonly SanitizationService $sanitizer,
    ) {
    }

    public function download(Request $request, Torrent $torrent): Response
    {
        $this->authorize('download', $torrent);

        if (! $torrent->hasTorrentFile()) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        try {
            $payload = $this->downloadService->buildPersonalizedPayload($torrent, $user);
        } catch (RuntimeException) {
            abort(404);
        }

        $this->userTorrentService->recordGrab($user, $torrent, CarbonImmutable::now());

        $baseFilename = Str::slug($torrent->name ?: 'torrent-'.$torrent->getKey()) ?: 'torrent-'.$torrent->getKey();
        $filename = $baseFilename.'.torrent';

        return response($payload, 200, [
            'Content-Type' => 'application/x-bittorrent',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Returns the magnet link as JSON for client-side copy helpers.
     */
    public function magnet(Request $request, Torrent $torrent): JsonResponse
    {
        $this->authorize('download', $torrent);

        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $infoHash = strtoupper($torrent->info_hash);
        $displayName = $this->sanitizer->sanitizeString($torrent->name ?? '');
        $displayName = $displayName !== '' ? $displayName : 'torrent-'.$torrent->getKey();

        $announceTemplate = (string) config('tracker.announce_url', '');
        $announce = '';

        if ($announceTemplate !== '') {
            $announce = $this->downloadService->buildTrackerUrlForUser($announceTemplate, $user);
            $announce = $this->sanitizer->sanitizeString($announce);
        }
        $additionalTrackers = array_filter((array) config('tracker.additional_trackers', []));

        $magnet = 'magnet:?xt=urn:btih:'.$infoHash;
        $magnet .= '&dn='.rawurlencode($displayName);

        if ($announce !== '') {
            $magnet .= '&tr='.rawurlencode($announce);
        }

        foreach ($additionalTrackers as $tracker) {
            $personalized = $this->downloadService->buildTrackerUrlForUser((string) $tracker, $user);
            $sanitized = $this->sanitizer->sanitizeString($personalized);

            if ($sanitized === '') {
                continue;
            }

            $magnet .= '&tr='.rawurlencode($sanitized);
        }

        return response()->json([
            'magnet' => $magnet,
        ]);
    }
}
