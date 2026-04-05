<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TorrentDetailsResource;
use App\Models\Torrent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'data' => (new TorrentDetailsResource($torrentModel, $user->passkey))->resolve(),
        ]);
    }
}
