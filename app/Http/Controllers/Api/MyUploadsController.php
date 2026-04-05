<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MyUploadsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $uploads = Torrent::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        return response()->json([
            'data' => $uploads->map(static fn (Torrent $torrent): array => [
                'id' => $torrent->id,
                'slug' => $torrent->slug,
                'name' => $torrent->name,
                'status' => $torrent->status->value,
                'moderation_reason' => $torrent->moderated_reason,
                'published_at' => $torrent->published_at?->toISOString(),
                'created_at' => $torrent->created_at?->toISOString(),
            ])->all(),
        ]);
    }
}
