<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class TorrentDetailsController extends Controller
{
    public function show(Request $request, int $torrent): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $torrentModel = Torrent::query()
            ->with(['category', 'uploader'])
            ->findOrFail($torrent);

        $this->authorize('view', $torrentModel);

        $uploader = $torrentModel->uploader;
        $category = $torrentModel->category;

        $rawFiles = data_get($torrentModel, 'files', []);
        $files = $rawFiles instanceof Collection ? $rawFiles : collect($rawFiles);

        $magnetUrl = sprintf(
            'magnet:?xt=urn:btih:%s&dn=%s&tr=%s&tr=%s',
            strtoupper((string) $torrentModel->info_hash),
            rawurlencode((string) $torrentModel->name),
            rawurlencode(sprintf(
                (string) config('tracker.announce_url', 'https://tracker.example/announce/%s'),
                $user->passkey
            )),
            rawurlencode((string) config('tracker.backup_announce_url', 'https://backup.example/announce'))
        );

        return response()->json([
            'data' => [
                'id' => $torrentModel->id,
                'slug' => $torrentModel->slug,
                'name' => $torrentModel->name,
                'description' => $torrentModel->description,
                'type' => $torrentModel->type,
                'info_hash' => strtolower((string) $torrentModel->info_hash),
                'size_bytes' => (int) ($torrentModel->size_bytes ?? 0),
                'size_human' => $torrentModel->formatted_size,
                'seeders' => (int) ($torrentModel->seeders ?? 0),
                'leechers' => (int) ($torrentModel->leechers ?? 0),
                'completed' => (int) ($torrentModel->completed ?? 0),
                'freeleech' => (bool) ($torrentModel->freeleech ?? false),
                'status' => $torrentModel->status->value,
                'uploaded_at' => $torrentModel->uploadedAtForDisplay()?->toISOString(),
                'uploaded_at_human' => $torrentModel->uploadedAtForDisplay()?->diffForHumans(),
                'file_count' => 0,
                'category' => $category
                    ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ]
                    : null,
                'uploader' => $uploader
                    ? [
                        'id' => $uploader->id,
                        'name' => $uploader->name,
                    ]
                    : null,
                'files' => $files->map(static function ($file): array {
                    return [
                        'path' => (string) data_get($file, 'path', ''),
                        'size_bytes' => (int) data_get($file, 'size', 0),
                    ];
                })->values()->all(),
                'download_url' => '/api/torrents/'.$torrentModel->id.'/download',
                'magnet_url' => $magnetUrl,
            ],
        ]);
    }
}
