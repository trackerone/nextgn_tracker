<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Torrents\ResolveTorrentAccessAction;
use App\Http\Requests\StoreTorrentFollowRequest;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\TorrentFollow;
use App\Services\Torrents\TorrentFollowMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TorrentFollowController extends Controller
{
    public function index(Request $request, TorrentFollowMatcher $matcher): View
    {
        /** @var \Illuminate\Support\Collection<int, TorrentFollow> $follows */
        $follows = $request->user()
            ->torrentFollows()
            ->orderByDesc('created_at')
            ->get();

        return view('account.follows', [
            'follows' => $follows,
            'matchesByFollowId' => $matcher->matchesForFollows($follows),
        ]);
    }

    public function store(StoreTorrentFollowRequest $request, TorrentFollowMatcher $matcher): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->torrentFollows()->create([
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

        TorrentFollow::query()->create([
            'user_id' => $request->user()->id,
            'title' => $title,
            'normalized_title' => $matcher->normalizedTitle($title),
            'type' => $this->nullableString($metadata['type'] ?? null),
            'resolution' => $this->nullableString($metadata['resolution'] ?? null),
            'source' => $this->nullableString($metadata['source'] ?? null),
            'year' => is_numeric($metadata['year'] ?? null) ? (int) $metadata['year'] : null,
        ]);

        return redirect()
            ->route('my.follows')
            ->with('status', 'Follow preference created from torrent.');
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
