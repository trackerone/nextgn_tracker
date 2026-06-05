<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Torrents\PublishTorrentAction;
use App\Actions\Torrents\RejectTorrentAction;
use App\Exceptions\InvalidTorrentStatusTransitionException;
use App\Http\Requests\ModerateTorrentRequest;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Services\Logging\AuditLogger;
use App\Support\Torrents\TorrentMetadataPresenter;
use App\Support\Torrents\TorrentModerationMetadataReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TorrentModerationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PublishTorrentAction $publishTorrentAction,
        private readonly RejectTorrentAction $rejectTorrentAction,
    ) {}

    public function index(): View
    {
        $this->authorize('viewModerationListings', Torrent::class);

        $pending = Torrent::query()
            ->select([
                'id',
                'user_id',
                'category_id',
                'name',
                'slug',
                'description',
                'type',
                'source',
                'resolution',
                'size_bytes',
                'file_count',
                'status',
                'original_filename',
                'uploaded_at',
                'updated_at',
                'imdb_id',
                'tmdb_id',
                'nfo_text',
            ])
            ->with([
                'category:id,name',
                'uploader:id,name',
                'metadata:id,torrent_id,title,year,type,resolution,source,release_group,language,audio_language,subtitle_language,subtitles,imdb_id,tmdb_id,nfo,raw_payload',
            ])
            ->pending()
            ->orderByDesc('uploaded_at')
            ->paginate(25);

        $pendingRows = $pending->getCollection();
        $metadata = TorrentMetadataView::mapByTorrentId($pendingRows);

        $recent = Torrent::query()
            ->select(['id', 'moderated_by', 'name', 'slug', 'status', 'moderated_at', 'moderated_reason'])
            ->with(['moderator:id,name'])
            ->moderated()
            ->latest('moderated_at')
            ->limit(10)
            ->get();

        return view('staff.torrents.moderation.index', [
            'pendingTorrents' => $pending,
            'recentTorrents' => $recent,
            'torrentMetadata' => $metadata,
            'metadataBadgesByTorrent' => $this->metadataBadgesByTorrentId($metadata),
            'metadataTypeLabelsByTorrent' => $this->metadataTypeLabelsByTorrentId($metadata),
            'metadataEnrichmentOutcome' => TorrentMetadataView::enrichmentOutcomeMapByTorrentId($pendingRows),
            'releaseAdviceByTorrent' => TorrentMetadataView::releaseAdviceMapByTorrentId($pendingRows),
            'moderationMetadataReview' => TorrentModerationMetadataReview::mapByTorrentId(
                $pendingRows,
                $metadata
            ),
        ]);
    }

    /**
     * @param  array<int, array<string, int|string|null>>  $metadataByTorrentId
     * @return array<int, array<int, string>>
     */
    private function metadataBadgesByTorrentId(array $metadataByTorrentId): array
    {
        $badges = [];

        foreach ($metadataByTorrentId as $torrentId => $metadata) {
            $badges[$torrentId] = TorrentMetadataPresenter::listingBadges($metadata);
        }

        return $badges;
    }

    /**
     * @param  array<int, array<string, int|string|null>>  $metadataByTorrentId
     * @return array<int, string|null>
     */
    private function metadataTypeLabelsByTorrentId(array $metadataByTorrentId): array
    {
        $labels = [];

        foreach ($metadataByTorrentId as $torrentId => $metadata) {
            $labels[$torrentId] = TorrentMetadataPresenter::typeLabel($metadata);
        }

        return $labels;
    }

    public function approve(Request $request, Torrent $torrent): RedirectResponse
    {
        $this->authorize('publish', $torrent);
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $this->publishTorrentAction->execute($torrent, $user);
        } catch (InvalidTorrentStatusTransitionException) {
            SecurityAuditLog::logAndWarn($user, 'torrent.moderation.invalid_transition', [
                'torrent_id' => $torrent->getKey(),
                'action' => 'approve',
                'current_status' => $torrent->status->value,
            ]);

            return redirect()
                ->route('staff.torrents.moderation.index')
                ->with('status', 'Only pending uploads can be approved.');
        }

        $this->auditLogger->log('torrent.approved', $torrent, [
            'moderated_by' => $user->id,
        ]);

        SecurityAuditLog::log($user, 'torrent.edit', [
            'torrent_id' => $torrent->getKey(),
            'action' => 'approve',
        ]);

        return redirect()->route('staff.torrents.moderation.index')->with('status', 'Torrent approved.');
    }

    public function reject(ModerateTorrentRequest $request, Torrent $torrent): RedirectResponse
    {
        $this->authorize('reject', $torrent);
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validated();

        try {
            $this->rejectTorrentAction->execute($torrent, $user, $data['reason']);
        } catch (InvalidTorrentStatusTransitionException) {
            SecurityAuditLog::logAndWarn($user, 'torrent.moderation.invalid_transition', [
                'torrent_id' => $torrent->getKey(),
                'action' => 'reject',
                'current_status' => $torrent->status->value,
            ]);

            return redirect()
                ->route('staff.torrents.moderation.index')
                ->with('status', 'Only pending uploads can be rejected.');
        }

        $this->auditLogger->log('torrent.rejected', $torrent, [
            'moderated_by' => $user->id,
            'reason' => $data['reason'],
        ]);

        SecurityAuditLog::log($user, 'torrent.edit', [
            'torrent_id' => $torrent->getKey(),
            'action' => 'reject',
            'reason' => $data['reason'],
        ]);

        return redirect()->route('staff.torrents.moderation.index')->with('status', 'Torrent rejected.');
    }

    public function softDelete(Request $request, Torrent $torrent): RedirectResponse
    {
        $this->authorize('moderate', $torrent);
        /** @var \App\Models\User $user */
        $user = $request->user();

        $torrent->forceFill([
            'status' => Torrent::STATUS_SOFT_DELETED,
            'moderated_by' => $user->id,
            'moderated_at' => Carbon::now(),
        ])->save();

        $this->auditLogger->log('torrent.soft_deleted', $torrent, [
            'moderated_by' => $user->id,
        ]);

        SecurityAuditLog::log($user, 'torrent.delete', [
            'torrent_id' => $torrent->getKey(),
            'action' => 'soft_delete',
        ]);

        return redirect()->route('staff.torrents.moderation.index')->with('status', 'Torrent soft-deleted.');
    }
}
