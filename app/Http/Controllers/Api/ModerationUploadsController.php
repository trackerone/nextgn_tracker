<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Torrents\PublishTorrentAction;
use App\Actions\Torrents\RejectTorrentAction;
use App\Exceptions\InvalidTorrentStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ModerationUploadsController extends Controller
{
    public function __construct(
        private readonly PublishTorrentAction $publishTorrentAction,
        private readonly RejectTorrentAction $rejectTorrentAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewModerationListings', Torrent::class);

        $status = (string) $request->query('status', Torrent::STATUS_PENDING);

        $query = Torrent::query()
            ->with('uploader')
            ->latest('created_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        /** @var EloquentCollection<int, Torrent> $uploads */
        $uploads = $query->get();

        return response()->json([
            'data' => $uploads->map(static function ($torrent): array {
                /** @var Torrent $torrent */
                return [
                    'id' => $torrent->id,
                    'slug' => $torrent->slug,
                    'name' => $torrent->name,
                    'status' => $torrent->status->value,
                    'uploader' => $torrent->uploader?->name,
                    'created_at' => $torrent->created_at?->toISOString(),
                ];
            })->all(),
        ]);
    }

    public function approve(Request $request, Torrent $torrent): JsonResponse
    {
        $this->authorize('publish', $torrent);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->publishTorrentAction->execute($torrent, $user);
        } catch (InvalidTorrentStatusTransitionException) {
            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        return response()->json([
            'data' => [
                'id' => $torrent->id,
                'status' => $torrent->status->value,
            ],
        ]);
    }

    public function reject(Request $request, Torrent $torrent): JsonResponse
    {
        $this->authorize('reject', $torrent);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->rejectTorrentAction->execute($torrent, $user, $data['reason'] ?? null);
        } catch (InvalidTorrentStatusTransitionException) {
            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        return response()->json([
            'data' => [
                'id' => $torrent->id,
                'status' => $torrent->status->value,
            ],
        ]);
    }
}
