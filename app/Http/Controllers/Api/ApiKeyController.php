<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
<<<<<< codex/harden-file-upload-surface-in-nextgn-tracker
=======
use Symfony\Component\HttpFoundation\Response;
>>>>>> main

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
<<<<<< codex/harden-file-upload-surface-in-nextgn-tracker
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
=======
        $user = $this->authorizeUser($request);
        return response()->json($user->apiKeys()->select(['id', 'label', 'created_at', 'last_used_at'])->latest()->get());
>>>>> main
    }

    public function store(Request $request): JsonResponse
    {
<<<<<< codex/harden-file-upload-surface-in-nextgn-tracker
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
=======
        $user = $this->authorizeUser($request);
        $data = $request->validate(['label' => ['nullable', 'string', 'max:120']]);
        $apiKey = ApiKey::query()->create([
            'user_id' => $user->id,
            'label' => $data['label'] ?? null,
            'key' => ApiKey::generateKey(),
        ]);
        return response()->json(
            $apiKey->only(['id', 'label', 'created_at', 'last_used_at']) + ['key' => $apiKey->key],
            Response::HTTP_CREATED
        );
>>>>>> main
    }

    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
<<<<<< codex/harden-file-upload-surface-in-nextgn-tracker
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
=======
        $user = $this->authorizeUser($request);
        abort_if((int) $apiKey->user_id !== (int) $user->id, Response::HTTP_NOT_FOUND);
        $apiKey->delete();
        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    private function authorizeUser(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);
        abort_if(! PermissionService::allow($user, 'api.access'), Response::HTTP_FORBIDDEN);
        return $user;
>>>>>> main
    }
}
