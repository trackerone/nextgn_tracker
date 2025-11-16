<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::hasRole($user, 'admin')) {
            abort(403);
        }

        SecurityAuditLog::log($user, 'admin.dashboard.view', [
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Admin area']);
    }
}
