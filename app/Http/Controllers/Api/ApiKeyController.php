<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizeUser($request);
        return response()->json($user->apiKeys()->select(['id', 'label', 'created_at', 'last_used_at'])->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
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
    }

    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
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
    }
}
