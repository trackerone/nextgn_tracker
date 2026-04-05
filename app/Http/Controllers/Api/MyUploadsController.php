<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyUploadResource;
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
            'data' => MyUploadResource::collection($uploads)->resolve(),
        ]);
    }
}
