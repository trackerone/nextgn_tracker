<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use App\Models\User;
use App\Services\TorrentDownloadService;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TorrentDownloadController extends Controller
{
    public function __construct(private readonly TorrentDownloadService $downloads) {}

    public function __invoke(string $torrent): StreamedResponse
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = Torrent::query()
            ->where(static function ($query) use ($torrent): void {
                $query->where('id', $torrent)->orWhere('slug', $torrent);
            })
            ->firstOrFail();

        $this->authorize('download', $model);

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
