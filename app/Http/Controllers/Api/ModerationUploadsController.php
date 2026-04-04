<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class ModerationUploadsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isStaff(), 403);

        $status = (string) $request->query('status', Torrent::STATUS_PENDING);

        $query = Torrent::query()->with('uploader')->latest('created_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $uploads = $query->get();

        return response()->json([
            'data' => $uploads->map(static fn (Torrent $torrent): array => [
                'id' => $torrent->id,
                'slug' => $torrent->slug,
                'name' => $torrent->name,
                'status' => $torrent->status,
                'uploader' => $torrent->uploader?->name,
                'created_at' => $torrent->created_at?->toISOString(),
            ])->all(),
        ]);
    }

    public function approve(Request $request, Torrent $torrent): JsonResponse
    {
        abort_unless($request->user()?->isStaff(), 403);

        if (! $torrent->canBeModerated()) {
            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        $torrent->forceFill([
            'status' => Torrent::STATUS_PUBLISHED,
            'is_approved' => true,
            'published_at' => Carbon::now(),
            'moderated_by' => $request->user()?->id,
            'moderated_at' => Carbon::now(),
            'moderated_reason' => null,
        ])->save();

        return response()->json(['data' => ['id' => $torrent->id, 'status' => $torrent->status]]);
    }

    public function reject(Request $request, Torrent $torrent): JsonResponse
    {
        abort_unless($request->user()?->isStaff(), 403);

        if (! $torrent->canBeModerated()) {
            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $torrent->forceFill([
            'status' => Torrent::STATUS_REJECTED,
            'is_approved' => false,
            'published_at' => null,
            'moderated_by' => $request->user()?->id,
            'moderated_at' => Carbon::now(),
            'moderated_reason' => $data['reason'] ?? null,
        ])->save();

        return response()->json(['data' => ['id' => $torrent->id, 'status' => $torrent->status]]);
    }
}
