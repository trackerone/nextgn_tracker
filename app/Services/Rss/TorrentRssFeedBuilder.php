<?php

declare(strict_types=1);

namespace App\Services\Rss;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\DownloadEligibilityService;
use App\Services\Tracker\DownloadEligibilityPolicy;
use DOMDocument;
use DOMElement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TorrentRssFeedBuilder
{
    private const BATCH_SIZE = 100;

    // Technical safety ceiling, not a configurable Sysop content policy.
    private const MAX_SCAN_BUDGET = 2000;
    public function __construct(
        private readonly DownloadEligibilityService $visibilityEligibility,
        private readonly DownloadEligibilityPolicy $ratioEligibility,
        private readonly TorrentRssFilterMatcher $filterMatcher,
    ) {}

    /**
     * @param  array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, year?: int|null, limit: int}  $filters
     */
    public function build(User $user, array $filters): string
    {
        $torrents = $this->eligibleTorrents($user, $filters);

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $rss = $document->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $document->appendChild($rss);

        $channel = $this->appendElement($document, $rss, 'channel');
        $this->appendElement($document, $channel, 'title', config('app.name', 'NextGN Tracker').' RSS');
        $this->appendElement($document, $channel, 'link', url('/torrents'));
        $this->appendElement($document, $channel, 'description', 'Metadata-aware torrent feed for eligible downloads.');
        $this->appendElement($document, $channel, 'lastBuildDate', now()->toRfc2822String());

        foreach ($torrents as $torrent) {
            $this->appendItem($document, $channel, $torrent, $user);
        }

        return (string) $document->saveXML();
    }

    /**
     * @param  array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, year?: int|null, limit: int}  $filters
     * @return Collection<int, Torrent>
     */
    private function eligibleTorrents(User $user, array $filters): Collection
    {
        /** @var Collection<int, Torrent> $matches */
        $matches = collect();
        $page = 1;
        $scanned = 0;
        $scanBudget = min(self::MAX_SCAN_BUDGET, max(100, (int) config('rss.scan_budget', self::MAX_SCAN_BUDGET)));
        $candidates = $this->candidateQuery($filters);

        do {
            $take = min(self::BATCH_SIZE, $scanBudget - $scanned);

            /** @var Collection<int, Torrent> $torrents */
            $torrents = (clone $candidates)
                ->with('metadata')
                ->latest('uploaded_at')
                ->latest('id')
                ->forPage($page, self::BATCH_SIZE)
                ->limit($take)
                ->get();

            $scanned += $torrents->count();

            foreach ($torrents as $torrent) {
                if (! $this->matchesFilters($torrent, $filters) || ! $this->canDownload($user, $torrent)) {
                    continue;
                }

                $matches->push($torrent);

                if ($matches->count() >= $filters['limit']) {
                    return $matches->values();
                }
            }

            $page++;
        } while ($torrents->count() === $take && $scanned < $scanBudget);

        return $matches->values();
    }

    /**
     * Apply only predicates that agree exactly with TorrentMetadataView.
     * Keep language/subtitle alias matching and title search in the shared
     * PHP matcher until they have a verified SQL-equivalent implementation.
     *
     * @param  array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, year?: int|null, limit: int}  $filters
     * @return Builder<Torrent>
     */
    private function candidateQuery(array $filters): Builder
    {
        $query = Torrent::query()->visible();

        if ($filters['category'] !== null) {
            $query->where('category_id', $filters['category']);
        }

        if ($filters['freeleech'] !== null) {
            $query->where('is_freeleech', $filters['freeleech']);
        }

        if (($filters['year'] ?? null) !== null) {
            $query->whereHas('metadata', static function (Builder $metadata) use ($filters): void {
                $metadata->where('year', $filters['year']);
            });
        }

        if ($filters['type'] !== '') {
            // TorrentMetadataView uses metadata.type whenever a metadata row
            // exists; it only falls back to torrents.type without that row.
            $type = mb_strtolower($filters['type']);
            $query->where(static function (Builder $query) use ($type): void {
                $query->whereHas('metadata', static function (Builder $metadata) use ($type): void {
                    $metadata->whereRaw('LOWER(torrent_metadata.type) = ?', [$type]);
                })->orWhere(static function (Builder $withoutMetadata) use ($type): void {
                    $withoutMetadata->whereDoesntHave('metadata')
                        ->whereRaw('LOWER(torrents.type) = ?', [$type]);
                });
            });
        }

        return $query;
    }

    /**
     * @param  array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, year?: int|null, limit: int}  $filters
     */
    private function matchesFilters(Torrent $torrent, array $filters): bool
    {
        return $this->filterMatcher->matches($torrent, $filters);
    }

    private function canDownload(User $user, Torrent $torrent): bool
    {
        return $this->visibilityEligibility->canDownload($user, $torrent)
            && $this->ratioEligibility->check($user, $torrent)['allowed'];
    }

    private function appendItem(DOMDocument $document, DOMElement $channel, Torrent $torrent, User $user): void
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);
        $title = (string) ($metadata['title'] ?: $torrent->name);
        $detailsUrl = route('torrents.show', $torrent, true);
        $downloadUrl = route('rss.torrents.download', [
            'token' => (string) $user->rss_token,
            'torrent' => (int) $torrent->getKey(),
        ], true);
        $item = $this->appendElement($document, $channel, 'item');

        $this->appendElement($document, $item, 'title', $title);
        $this->appendElement($document, $item, 'link', $detailsUrl);
        $this->appendElement($document, $item, 'guid', $detailsUrl)->setAttribute('isPermaLink', 'true');
        $this->appendElement($document, $item, 'pubDate', ($torrent->uploadedAtForDisplay() ?? $torrent->created_at ?? now())->toRfc2822String());
        $this->appendElement($document, $item, 'description', $this->description($torrent, $metadata));

        if (is_string($metadata['type'] ?? null)) {
            $this->appendElement($document, $item, 'category', $metadata['type']);
        }

        $enclosure = $this->appendElement($document, $item, 'enclosure');
        $enclosure->setAttribute('url', $downloadUrl);
        $enclosure->setAttribute('type', 'application/x-bittorrent');
        $enclosure->setAttribute('length', (string) max(0, (int) $torrent->size_bytes));
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function description(Torrent $torrent, array $metadata): string
    {
        $parts = array_filter([
            'Type: '.(string) ($metadata['type'] ?? ''),
            'Resolution: '.(string) ($metadata['resolution'] ?? ''),
            'Source: '.(string) ($metadata['source'] ?? ''),
            'Release group: '.(string) ($metadata['release_group'] ?? ''),
            'Language: '.(string) ($metadata['language'] ?? ''),
            'Audio language: '.(string) ($metadata['audio_language'] ?? ''),
            'Subtitle language: '.(string) ($metadata['subtitle_language'] ?? ''),
            'Subtitles: '.(string) ($metadata['subtitles'] ?? ''),
            'Year: '.(string) ($metadata['year'] ?? ''),
            'Size: '.$torrent->formatted_size,
            ((bool) $torrent->is_freeleech) ? 'Freeleech: yes' : null,
        ], static fn (mixed $value): bool => is_string($value) && ! str_ends_with($value, ': '));

        return implode(' | ', $parts);
    }

    private function appendElement(DOMDocument $document, DOMElement $parent, string $name, string|int|null $value = null): DOMElement
    {
        $element = $document->createElement($name);

        if ($value !== null) {
            $element->appendChild($document->createTextNode((string) $value));
        }

        $parent->appendChild($element);

        return $element;
    }
}
