<?php

declare(strict_types=1);

namespace App\Services\Metadata\Providers;

use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

final class OpenLibraryMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(private readonly HttpFactory $http) {}

    public function providerKey(): string
    {
        return 'open_library';
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $lookup->mediaType === 'book' && filled($lookup->title);
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        if (! $this->supports($lookup) || ! Cache::add('metadata.provider.open_library.request', true, 1)) {
            return ExternalMetadataResult::skipped($this->providerKey());
        }

        $response = $this->http->acceptJson()->timeout(5)->get('https://openlibrary.org/search.json', [
            'title' => $lookup->title,
            'fields' => 'key,title,author_name,first_publish_year',
            'limit' => 10,
        ]);
        $books = $response->successful() ? $response->json('docs') : null;
        $matches = [];

        foreach (is_array($books) ? $books : [] as $book) {
            $title = is_array($book) ? ($book['title'] ?? null) : null;
            if (! is_array($book) || ! is_string($title) || mb_strtolower(trim($title)) !== mb_strtolower(trim((string) $lookup->title))) {
                continue;
            }

            $id = $book['key'] ?? null;
            $year = $book['first_publish_year'] ?? null;
            if (! is_string($id) || preg_match('~^/works/OL[0-9]+W$~D', $id) !== 1 || ($lookup->year !== null && $lookup->year !== $year)) {
                continue;
            }

            $authors = $book['author_name'] ?? [];
            $matches[$id] = new ExternalMetadataResult(
                provider: $this->providerKey(),
                found: true,
                title: $title,
                year: is_int($year) ? $year : null,
                mediaType: 'book',
                rawPayload: ['catalog_id' => $id, 'creators' => is_array($authors) ? array_values(array_filter($authors, 'is_string')) : []],
            );
        }

        return count($matches) === 1 ? array_values($matches)[0] : ExternalMetadataResult::skipped($this->providerKey(), 'No unique book match.');
    }
}
