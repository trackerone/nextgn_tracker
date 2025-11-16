<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'staff', 'can:view-logs']);
    }

    public function index(Request $request): View
    {
        $query = AuditLog::query()->with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }

        if ($request->filled('from')) {
            $from = Carbon::parse($request->input('from'), config('app.timezone'));
            $query->where('created_at', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = Carbon::parse($request->input('to'), config('app.timezone'));
            $query->where('created_at', '<=', $to);
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('admin.logs.audit.index', [
            'logs' => $logs,
            'filters' => $request->only(['user_id', 'action', 'target_type', 'from', 'to']),
        ]);
    }

    public function show(AuditLog $log): View
    {
        $log->loadMissing('user');

        return view('admin.logs.audit.show', [
            'log' => $log,
            'target' => $this->resolveTarget($log),
        ]);
    }

    private function resolveTarget(AuditLog $log): ?Model
    {
        if ($log->target_type === null || $log->target_id === null) {
            return null;
        }

        if (!class_exists($log->target_type)) {
            return null;
        }

        if (!is_subclass_of($log->target_type, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $log->target_type;

        return $modelClass::query()->find($log->target_id);
    }
}
