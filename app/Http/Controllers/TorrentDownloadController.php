<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Torrent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TorrentDownloadController extends Controller
{
    public function download(Request $request, string $torrent): StreamedResponse
    {
        $model = $this->resolveTorrent($torrent);

        $disk = (string) config('upload.torrents.disk', 'torrents');
        $path = $model->torrentStoragePath();

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $filename = ($model->slug ?: (string) $model->getKey()) . '.torrent';

        return Storage::disk($disk)->download($path, $filename, [
            'Content-Type' => 'application/x-bittorrent',
        ]);
    }

    public function magnet(Request $request, string $torrent): JsonResponse
    {
        $model = $this->resolveTorrent($torrent);

        $announceUrl = (string) config('tracker.announce_url', '');
        $additional = (array) config('tracker.additional_trackers', []);

        $xt = 'xt=urn:btih:' . strtoupper((string) $model->info_hash);

        $params = [
            $xt,
            'dn=' . rawurlencode((string) $model->name),
        ];

        if ($announceUrl !== '') {
            $params[] = 'tr=' . rawurlencode($announceUrl);
        }

        foreach ($additional as $tracker) {
            if (is_string($tracker) && $tracker !== '') {
                $params[] = 'tr=' . rawurlencode($tracker);
            }
        }

        $magnet = 'magnet:?' . implode('&', $params);

        return response()->json([
            'magnet' => $magnet,
        ]);
    }

    private function resolveTorrent(string $torrent): Torrent
    {
        return Torrent::query()
            ->where('id', $torrent)
            ->orWhere('slug', $torrent)
            ->firstOrFail();
    }
}
