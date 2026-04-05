<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Torrents\PublishTorrentAction;
use App\Actions\Torrents\RejectTorrentAction;
use App\Enums\TorrentStatus;
use App\Exceptions\InvalidTorrentStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiModerateTorrentRequest;
use App\Http\Requests\ModerationUploadsIndexRequest;
use App\Http\Resources\ModerationTorrentResource;
use App\Http\Resources\TorrentStatusResource;
use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ModerationUploadsController extends Controller
{
    public function __construct(
        private readonly PublishTorrentAction $publishTorrentAction,
        private readonly RejectTorrentAction $rejectTorrentAction,
    ) {}

    public function index(ModerationUploadsIndexRequest $request): JsonResponse
    {
        $this->authorize('viewModerationListings', Torrent::class);

        $status = (string) ($request->validated('status') ?? TorrentStatus::Pending->value);

        $query = Torrent::query()
            ->with('uploader')
            ->latest('created_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        /** @var EloquentCollection<int, Torrent> $uploads */
        $uploads = $query->get();

        return response()->json([
            'data' => ModerationTorrentResource::collection($uploads)->resolve(),
        ]);
    }

    public function approve(Request $request, Torrent $torrent): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        try {
            $this->authorize('publish', $torrent);
        } catch (AuthorizationException $exception) {
            SecurityAuditLog::logAndWarn($user, 'torrent.moderation.unauthorized', [
                'route' => (string) ($request->route()?->getName() ?? ''),
                'torrent' => (string) $torrent->getKey(),
            ]);

            throw $exception;
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $this->publishTorrentAction->execute($torrent, $user);
        } catch (InvalidTorrentStatusTransitionException) {
            SecurityAuditLog::logAndWarn($user, 'torrent.moderation.invalid_transition', [
                'torrent_id' => $torrent->getKey(),
                'action' => 'approve',
                'current_status' => $torrent->status->value,
            ]);

            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        return response()->json([
            'data' => (new TorrentStatusResource($torrent))->resolve(),
        ]);
    }

    public function reject(ApiModerateTorrentRequest $request, Torrent $torrent): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        try {
            $this->authorize('reject', $torrent);
        } catch (AuthorizationException $exception) {
            SecurityAuditLog::logAndWarn($user, 'torrent.moderation.unauthorized', [
                'route' => (string) ($request->route()?->getName() ?? ''),
                'torrent' => (string) $torrent->getKey(),
            ]);

            throw $exception;
        }

        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();

        try {
            $this->rejectTorrentAction->execute($torrent, $user, $data['reason'] ?? null);
        } catch (InvalidTorrentStatusTransitionException) {
            SecurityAuditLog::logAndWarn($user, 'torrent.moderation.invalid_transition', [
                'torrent_id' => $torrent->getKey(),
                'action' => 'reject',
                'current_status' => $torrent->status->value,
            ]);

            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        return response()->json([
            'data' => (new TorrentStatusResource($torrent))->resolve(),
        ]);
    }
}
