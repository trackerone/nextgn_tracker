<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Torrents\ResolveTorrentAccessAction;
use App\Http\Requests\StoreTorrentFollowRequest;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\TorrentFollow;
use App\Services\Torrents\PersonalizedDiscoveryFeedBuilder;
use App\Services\Torrents\TorrentFollowInbox;
use App\Services\Torrents\TorrentFollowMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TorrentFollowController extends Controller
{
    public function index(Request $request, TorrentFollowInbox $inbox, PersonalizedDiscoveryFeedBuilder $feed): View
    {
        /** @var \Illuminate\Support\Collection<int, TorrentFollow> $follows */
        $follows = $request->user()
            ->torrentFollows()
            ->orderByDesc('created_at')
            ->get();

        $checkedAt = now()->toImmutable();
        $inboxItems = $inbox->build($follows);

        foreach ($inboxItems as $key => $item) {
            $inboxItems[$key]['families'] = $feed->families($item['allMatches'], $item['newMatches']);
        }

        $inbox->markAsSeen($follows, $checkedAt);

        return view('account.follows', [
            'inboxItems' => $inboxItems,
            'newMatchCount' => $inbox->totalNewCount($inboxItems),
        ]);
    }

    public function store(StoreTorrentFollowRequest $request, TorrentFollowMatcher $matcher): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->torrentFollows()->create([
            'last_checked_at' => now(),
            'title' => (string) $validated['title'],
            'normalized_title' => $matcher->normalizedTitle((string) $validated['title']),
            'type' => $this->nullableString($validated['type'] ?? null),
            'resolution' => $this->nullableString($validated['resolution'] ?? null),
            'source' => $this->nullableString($validated['source'] ?? null),
            'year' => isset($validated['year']) ? (int) $validated['year'] : null,
        ]);

        return redirect()
            ->route('my.follows')
            ->with('status', 'Follow preference saved.');
    }

    public function storeFromTorrent(Request $request, string $torrent, TorrentFollowMatcher $matcher): RedirectResponse
    {
        $model = app(ResolveTorrentAccessAction::class)->execute($torrent, 'view', ['metadata']);
        $metadata = TorrentMetadataView::forTorrent($model);

        $title = $this->nullableString($metadata['title'] ?? null) ?? $model->name;

        $type = $this->nullableString($metadata['type'] ?? null);
        $series = in_array($type, ['tv', 'series', 'episode', 'tv_episode'], true);
        if ($series) {
            $seriesTitle = preg_split('/[\s._-]+(?:s\d{1,2}(?:e\d{1,3})?|\d{1,2}x\d{1,3})(?![a-z0-9])/i', $title, 2);
            $title = $seriesTitle[0] ?? $title;
        }

        $request->user()->torrentFollows()->firstOrCreate([
            'normalized_title' => $matcher->normalizedTitle($title),
            'type' => $type,
            'resolution' => null,
            'source' => null,
            'year' => ! $series && is_numeric($metadata['year'] ?? null) ? (int) $metadata['year'] : null,
        ], [
            'title' => $title,
            'last_checked_at' => now(),
        ]);

        return redirect()
            ->route('my.follows')
            ->with('status', 'You are following this title.');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
