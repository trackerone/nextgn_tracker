<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Torrents\NfoParser;
use Tests\TestCase;

class NfoParserTest extends TestCase
{
    public function test_parse_returns_sanitized_text_and_detects_ids(): void
    {
        $parser = app(NfoParser::class);
        $payload = "My Awesome Release tt1234567\nhttps://www.themoviedb.org/movie/98765\n<script>alert('x')</script>\x01";

        $result = $parser->parse($payload);

        $this->assertSame('tt1234567', $result['imdb_id']);
        $this->assertSame('98765', $result['tmdb_id']);
        $this->assertNotNull($result['sanitized_text']);
        $this->assertStringNotContainsString('<script>', $result['sanitized_text']);
    }

    public function test_parse_handles_missing_text(): void
    {
        $parser = app(NfoParser::class);

        $result = $parser->parse(null);

        $this->assertNull($result['sanitized_text']);
        $this->assertNull($result['imdb_id']);
        $this->assertNull($result['tmdb_id']);
    }
}
