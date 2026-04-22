<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Torrents\ResolveTorrentAccessAction;
use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\TorrentDownloadService;
use App\Services\Tracker\DownloadEligibilityPolicy;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TorrentDownloadController extends Controller
{
    public function __construct(
        private readonly TorrentDownloadService $downloads,
        private readonly DownloadEligibilityPolicy $downloadEligibilityPolicy,
    ) {}

    public function __invoke(string $torrent): StreamedResponse|JsonResponse
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = app(ResolveTorrentAccessAction::class)->execute($torrent, 'download');

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
}
