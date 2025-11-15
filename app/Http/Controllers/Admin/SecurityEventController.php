<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SecurityEventController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'staff', 'can:view-logs']);
    }

    public function index(Request $request): View
    {
        $query = SecurityEvent::query()->with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', strtolower($request->input('severity')));
        }

        if ($request->filled('from')) {
            $from = Carbon::parse($request->input('from'), config('app.timezone'));
            $query->where('created_at', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = Carbon::parse($request->input('to'), config('app.timezone'));
            $query->where('created_at', '<=', $to);
        }

        $events = $query->paginate(50)->withQueryString();

        return view('admin.logs.security.index', [
            'events' => $events,
            'filters' => $request->only(['user_id', 'event_type', 'severity', 'from', 'to']),
        ]);
    }

    public function show(SecurityEvent $event): View
    {
        $event->loadMissing('user');

        return view('admin.logs.security.show', [
            'event' => $event,
        ]);
    }
}
