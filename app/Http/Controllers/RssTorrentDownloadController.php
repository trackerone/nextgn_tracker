<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use App\Services\TorrentDownloadService;
use App\Services\Torrents\DownloadEligibilityService;
use App\Services\Tracker\DownloadEligibilityPolicy;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RssTorrentDownloadController extends Controller
{
    public function __construct(
        private readonly TorrentDownloadService $downloads,
        private readonly DownloadEligibilityService $visibilityEligibility,
        private readonly DownloadEligibilityPolicy $downloadEligibilityPolicy,
    ) {}

    public function __invoke(Request $request, string $token, string $torrent): StreamedResponse
    {
        $user = User::query()
            ->where('rss_token', $token)
            ->firstOrFail();

        $model = Torrent::query()
            ->visible()
            ->where('is_visible', true)
            ->whereKey($torrent)
            ->firstOrFail();

        if (! $this->visibilityEligibility->canDownload($user, $model)) {
            abort(404);
        }

        $eligibility = $this->downloadEligibilityPolicy->check($user, $model);

        if (! $eligibility['allowed']) {
            SecurityAuditLog::logAndWarn($user, 'torrent.download.denied', [
                'reason' => $eligibility['reason'],
                'torrent_id' => (int) $model->getKey(),
                'route' => (string) ($request->route()?->getName() ?? ''),
            ]);

            abort(403);
        }

        try {
            $payload = $this->downloads->buildPersonalizedPayload($model, $user);
        } catch (RuntimeException) {
            abort(404);
        }

        return $this->downloads->streamPayload($model, $payload, [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
