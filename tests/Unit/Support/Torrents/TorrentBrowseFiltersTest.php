<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Torrents;

use App\Support\Torrents\TorrentBrowseFilters;
use PHPUnit\Framework\TestCase;

final class TorrentBrowseFiltersTest extends TestCase
{
    public function test_it_serializes_normalized_intent_for_reuse(): void
    {
        $filters = TorrentBrowseFilters::fromInput([
            'q' => 'matrix',
            'type' => 'movie',
            'release_group' => 'ntb',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish,english,german',
            'resolution' => '1080P',
            'source' => 'web-dl',
            'category_id' => '12',
            'order' => 'seeders',
            'direction' => 'asc',
        ]);

        $this->assertSame([
            'q' => 'matrix',
            'type' => 'movie',
            'release_group' => 'NTB',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish,english,german',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'year' => null,
            'category_id' => 12,
            'order' => 'seeders',
            'direction' => 'asc',
        ], $filters->toArray());

        $this->assertSame([
            'q' => 'matrix',
            'type' => 'movie',
            'release_group' => 'NTB',
            'language' => 'english',
            'audio_language' => 'japanese',
            'subtitle_language' => 'danish,english,german',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
            'category_id' => 12,
            'order' => 'seeders',
            'direction' => 'asc',
        ], $filters->queryParams());
    }
}
