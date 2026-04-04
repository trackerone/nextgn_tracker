<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use App\Models\User;
use App\Services\TorrentDownloadService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class TorrentDownloadController extends Controller
{
    public function __construct(private readonly TorrentDownloadService $downloads) {}

    public function __invoke(string $torrent): Response
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = Torrent::query()
            ->visible()
            ->where(static function ($query) use ($torrent): void {
                $query->where('id', $torrent)->orWhere('slug', $torrent);
            })
            ->firstOrFail();

        try {
            $payload = $this->downloads->buildPersonalizedPayload($model, $user);
        } catch (RuntimeException) {
            abort(404);
        }

        $filename = ($model->slug ?: (string) $model->getKey()).'.torrent';

        return response($payload, 200, [
            'Content-Type' => 'application/x-bittorrent',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
