<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Torrents\ResolveTorrentAccessAction;
use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use App\Services\TorrentDownloadService;
use App\Services\Tracker\DownloadEligibilityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TorrentDownloadController extends Controller
{
    public function __construct(
        private readonly TorrentDownloadService $downloads,
        private readonly DownloadEligibilityPolicy $downloadEligibilityPolicy,
    ) {}

    public function download(Request $request, string $torrent): StreamedResponse|JsonResponse
    {
        $model = $this->resolveTorrent($torrent);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }
        $eligibility = $this->downloadEligibilityPolicy->check($user, $model);

        if (! $eligibility['allowed']) {
            SecurityAuditLog::logAndWarn($user, 'torrent.download.denied', [
                'reason' => $eligibility['reason'],
                'torrent_id' => (int) $model->getKey(),
                'route' => (string) (request()->route()?->getName() ?? ''),
            ]);

            return response()->json([
                'message' => 'Download not allowed',
                'reason' => $eligibility['reason'],
            ], 403);
        }

        try {
            $payload = $this->downloads->buildPersonalizedPayload($model, $user);
        } catch (RuntimeException) {
            abort(404);
        }

        $filename = ($model->slug ?: (string) $model->getKey()).'.torrent';

        return response()->streamDownload(
            static function () use ($payload): void {
                echo $payload;
            },
            $filename,
            [
                'Content-Type' => 'application/x-bittorrent',
            ]
        );
    }

    public function magnet(Request $request, string $torrent): JsonResponse
    {
        $model = $this->resolveTorrent($torrent);

        $announceUrl = (string) config('tracker.announce_url', '');
        $additional = (array) config('tracker.additional_trackers', []);

        $xt = 'xt=urn:btih:'.strtoupper((string) $model->info_hash);

        $params = [
            $xt,
            'dn='.rawurlencode((string) $model->name),
        ];

        if ($announceUrl !== '') {
            $params[] = 'tr='.rawurlencode($announceUrl);
        }

        foreach ($additional as $tracker) {
            if (is_string($tracker) && $tracker !== '') {
                $params[] = 'tr='.rawurlencode($tracker);
            }
        }

        $magnet = 'magnet:?'.implode('&', $params);

        return response()->json([
            'magnet' => $magnet,
        ]);
    }

    private function resolveTorrent(string $torrent): Torrent
    {
        return app(ResolveTorrentAccessAction::class)->execute($torrent, 'download');
    }
}
