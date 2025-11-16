<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureApiAccess($user);

        $keys = $user->apiKeys()
            ->orderByDesc('created_at')
            ->get(['id', 'label', 'created_at', 'last_used_at']);

        return response()->json([
            'data' => $keys->map(static fn (ApiKey $key): array => [
                'id' => $key->id,
                'label' => $key->label,
                'created_at' => $key->created_at,
                'last_used_at' => $key->last_used_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureApiAccess($user);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $plainKey = ApiKey::generateKey();

        $apiKey = $user->apiKeys()->create([
            'key' => $plainKey,
            'label' => $validated['label'] ?? null,
        ]);

        return response()->json([
            'id' => $apiKey->id,
            'label' => $apiKey->label,
            'created_at' => $apiKey->created_at,
            'last_used_at' => $apiKey->last_used_at,
            'key' => $plainKey,
        ], 201);
    }

    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        $user = $request->user();
        $this->ensureApiAccess($user);

        if ($apiKey->user_id !== $user->id) {
            abort(404);
        }

        $apiKey->delete();

        return response()->json([
            'status' => 'deleted',
        ]);
    }

    private function ensureApiAccess(?User $user): void
    {
        if ($user === null || ! PermissionService::allow($user, 'api.access')) {
            abort(403);
        }
    }
}
