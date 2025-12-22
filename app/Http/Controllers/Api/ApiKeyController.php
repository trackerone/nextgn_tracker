<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $keys = ApiKey::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->get(['id', 'label', 'created_at']);

        return response()->json([
            'data' => $keys->map(static fn (ApiKey $key): array => [
                'id' => $key->getKey(),
                'label' => (string) $key->label,
                'created_at' => optional($key->created_at)?->toISOString(),
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        // Test-driven permission: users with role === null are denied.
        if ($user->getAttribute('role') === null) {
            abort(403);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        $plainKey = Str::random(64);

        $apiKey = ApiKey::query()->create([
            'user_id' => $user->getKey(),
            'label' => $validated['label'],
            // Your schema requires api_keys.key NOT NULL:
            'key' => $plainKey,
        ]);

        return response()->json([
            'id' => $apiKey->getKey(),
            'key' => $plainKey,
        ], 201);
    }

    public function destroy(Request $request, int $apiKey): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $key = ApiKey::query()
            ->where('user_id', $user->getKey())
            ->whereKey($apiKey)
            ->first();

        if ($key === null) {
            abort(404);
        }

        $key->delete();

        return response()->json(['status' => 'deleted']);
    }
}
