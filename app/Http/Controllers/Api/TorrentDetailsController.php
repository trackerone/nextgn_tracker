<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Torrents\ResolveTorrentAccessAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\TorrentDetailsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TorrentDetailsController extends Controller
{
    public function show(Request $request, int $torrent): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $torrentModel = app(ResolveTorrentAccessAction::class)->execute($torrent, 'view', ['category', 'uploader']);

        return response()->json([
            'data' => (new TorrentDetailsResource($torrentModel, $user->passkey))->resolve(),
        ]);
    }
}
