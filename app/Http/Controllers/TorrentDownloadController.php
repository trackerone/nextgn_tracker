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

        return $this->downloads->streamPayload($model, $payload);
    }

    private function resolveTorrent(string $torrent): Torrent
    {
        return app(ResolveTorrentAccessAction::class)->execute($torrent, 'download');
    }
}
