<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Torrent;
use App\Services\TorrentDownloadService;
use App\Services\UserTorrentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TorrentDownloadController extends Controller
{
    public function __construct(
        private readonly TorrentDownloadService $downloadService,
        private readonly UserTorrentService $userTorrentService,
    ) {
    }

    public function __invoke(Request $request, Torrent $torrent): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $torrent->isApproved() || $torrent->isBanned() || ! $torrent->hasTorrentFile()) {
            throw new NotFoundHttpException();
        }

        try {
            $payload = $this->downloadService->buildPersonalizedPayload($torrent, $user);
        } catch (RuntimeException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        $this->userTorrentService->recordGrab($user, $torrent, CarbonImmutable::now());

        $baseFilename = Str::slug($torrent->name ?: 'torrent-'.$torrent->getKey()) ?: 'torrent-'.$torrent->getKey();
        $filename = $baseFilename.'.torrent';

        return response($payload, 200, [
            'Content-Type' => 'application/x-bittorrent',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
