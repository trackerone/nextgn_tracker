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
use Illuminate\Support\Collection;

final class TorrentRssFeedBuilder
{
    /**
     * @var array<string, string>
     */
    private const LANGUAGE_ALIASES = [
        'da' => 'da',
        'danish' => 'da',
        'dansk' => 'da',
        'no' => 'no',
        'norwegian' => 'no',
        'norsk' => 'no',
        'nb' => 'nb',
        'bokmal' => 'nb',
        'bokmaal' => 'nb',
        'nn' => 'nn',
        'nynorsk' => 'nn',
        'sv' => 'sv',
        'swedish' => 'sv',
        'svensk' => 'sv',
        'fi' => 'fi',
        'finnish' => 'fi',
        'suomi' => 'fi',
        'en' => 'en',
        'english' => 'en',
        'engelsk' => 'en',
    ];

    public function __construct(
        private readonly DownloadEligibilityService $visibilityEligibility,
        private readonly DownloadEligibilityPolicy $ratioEligibility,
    ) {}

    /**
     * @param  array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, limit: int}  $filters
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
     * @param  array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, limit: int}  $filters
     * @return Collection<int, Torrent>
     */
    private function eligibleTorrents(User $user, array $filters): Collection
    {
        /** @var Collection<int, Torrent> $matches */
        $matches = collect();
        $page = 1;
        $perPage = 100;

        do {
            /** @var Collection<int, Torrent> $torrents */
            $torrents = Torrent::query()
                ->visible()
                ->where('is_visible', true)
                ->with('metadata')
                ->latest('uploaded_at')
                ->latest('id')
                ->forPage($page, $perPage)
                ->get();

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
        } while ($torrents->isNotEmpty());

        return $matches->values();
    }

    /**
     * @param  array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, limit: int}  $filters
     */
    private function matchesFilters(Torrent $torrent, array $filters): bool
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);

        if ($filters['category'] !== null && (int) $torrent->category_id !== $filters['category']) {
            return false;
        }

        if ($filters['freeleech'] !== null && (bool) ($torrent->is_freeleech ?? $torrent->freeleech ?? false) !== $filters['freeleech']) {
            return false;
        }

        foreach (['type', 'resolution', 'source', 'release_group'] as $field) {
            if ($filters[$field] !== '' && mb_strtolower((string) ($metadata[$field] ?? '')) !== mb_strtolower($filters[$field])) {
                return false;
            }
        }

        foreach (['language', 'audio_language', 'subtitle_language'] as $field) {
            if ($filters[$field] !== '' && ! $this->languageFieldMatches((string) ($metadata[$field] ?? ''), $filters[$field])) {
                return false;
            }
        }

        if ($filters['subtitles'] !== '' && ! $this->languageFieldMatches((string) ($metadata['subtitles'] ?? ''), $filters['subtitles'])) {
            return false;
        }

        if ($filters['q'] === '') {
            return true;
        }

        $needle = mb_strtolower($filters['q']);
        $haystack = mb_strtolower(trim(implode(' ', [
            (string) $torrent->name,
            (string) ($metadata['title'] ?? ''),
            (string) ($metadata['release_group'] ?? ''),
        ])));

        return str_contains($haystack, $needle);
    }

    private function languageFieldMatches(string $metadataValue, string $filterValue): bool
    {
        $metadataLanguages = $this->normalizeLanguages($metadataValue);

        if ($metadataLanguages === []) {
            return false;
        }

        foreach ($this->normalizeLanguages($filterValue) as $filterLanguage) {
            if (in_array($filterLanguage, $metadataLanguages, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function normalizeLanguages(string $value): array
    {
        $normalized = [];

        foreach (preg_split('/[,;\/|]+/', $value) ?: [] as $part) {
            $language = mb_strtolower(trim($part));

            if ($language === '') {
                continue;
            }

            $language = str_replace(['_', '-'], ' ', $language);
            $language = preg_replace('/\s+/', ' ', $language) ?? $language;
            $language = self::LANGUAGE_ALIASES[$language] ?? $language;
            $language = self::LANGUAGE_ALIASES[strtok($language, ' ') ?: $language] ?? $language;
            $normalized[] = $language;
        }

        return array_values(array_unique($normalized));
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
            ((bool) ($torrent->is_freeleech ?? $torrent->freeleech ?? false)) ? 'Freeleech: yes' : null,
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
