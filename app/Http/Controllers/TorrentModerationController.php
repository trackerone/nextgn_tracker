<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Services\Logging\AuditLogger;
use App\Services\PermissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TorrentModerationController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): View
    {
        $pending = Torrent::query()
            ->with(['uploader'])
            ->pending()
            ->orderByDesc('uploaded_at')
            ->paginate(25);

        $recent = Torrent::query()
            ->with(['uploader', 'moderator'])
            ->moderated()
            ->latest('moderated_at')
            ->limit(10)
            ->get();

        return view('staff.torrents.moderation.index', [
            'pendingTorrents' => $pending,
            'recentTorrents' => $recent,
        ]);
    }

    public function approve(Request $request, Torrent $torrent): RedirectResponse
    {
        $this->authorize('moderate', $torrent);

        if (! PermissionService::allow($request->user(), 'torrent.edit', $torrent)) {
            abort(403);
        }

        $torrent->forceFill([
            'status' => Torrent::STATUS_APPROVED,
            'moderated_by' => $request->user()?->id,
            'moderated_at' => Carbon::now(),
            'moderated_reason' => null,
        ])->save();

        $this->auditLogger->log('torrent.approved', $torrent, [
            'moderated_by' => $request->user()?->id,
        ]);

        SecurityAuditLog::log($request->user(), 'torrent.edit', [
            'torrent_id' => $torrent->getKey(),
            'action' => 'approve',
        ]);

        return redirect()->route('staff.torrents.moderation.index')->with('status', 'Torrent approved.');
    }

    public function reject(Request $request, Torrent $torrent): RedirectResponse
    {
        $this->authorize('moderate', $torrent);

        if (! PermissionService::allow($request->user(), 'torrent.edit', $torrent)) {
            abort(403);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $torrent->forceFill([
            'status' => Torrent::STATUS_REJECTED,
            'moderated_by' => $request->user()?->id,
            'moderated_at' => Carbon::now(),
            'moderated_reason' => $data['reason'],
        ])->save();

        $this->auditLogger->log('torrent.rejected', $torrent, [
            'moderated_by' => $request->user()?->id,
            'reason' => $data['reason'],
        ]);

        SecurityAuditLog::log($request->user(), 'torrent.edit', [
            'torrent_id' => $torrent->getKey(),
            'action' => 'reject',
            'reason' => $data['reason'],
        ]);

        return redirect()->route('staff.torrents.moderation.index')->with('status', 'Torrent rejected.');
    }

    public function softDelete(Request $request, Torrent $torrent): RedirectResponse
    {
        $this->authorize('moderate', $torrent);

        if (! PermissionService::allow($request->user(), 'torrent.delete', $torrent)) {
            abort(403);
        }

        $torrent->forceFill([
            'status' => Torrent::STATUS_SOFT_DELETED,
            'moderated_by' => $request->user()?->id,
            'moderated_at' => Carbon::now(),
        ])->save();

        $this->auditLogger->log('torrent.soft_deleted', $torrent, [
            'moderated_by' => $request->user()?->id,
        ]);

        SecurityAuditLog::log($request->user(), 'torrent.delete', [
            'torrent_id' => $torrent->getKey(),
            'action' => 'soft_delete',
        ]);

        return redirect()->route('staff.torrents.moderation.index')->with('status', 'Torrent soft-deleted.');
    }
}
