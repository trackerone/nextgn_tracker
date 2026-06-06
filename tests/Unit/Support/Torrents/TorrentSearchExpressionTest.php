<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Torrents;

use App\Support\Torrents\TorrentSearchExpression;
use PHPUnit\Framework\TestCase;

final class TorrentSearchExpressionTest extends TestCase
{
    public function test_it_parses_metadata_directives_and_free_text(): void
    {
        $expression = TorrentSearchExpression::fromQuery('Planet Earth rg:ntb source:web-dl res:2160P year:2024');

        $this->assertSame('Planet Earth', $expression->text);
        $this->assertSame('NTB', $expression->releaseGroup);
        $this->assertSame('WEB-DL', $expression->source);
        $this->assertSame('2160p', $expression->resolution);
        $this->assertNull($expression->language);
        $this->assertNull($expression->audioLanguage);
        $this->assertNull($expression->subtitleLanguage);
        $this->assertSame(2024, $expression->year);
        $this->assertTrue($expression->hasMetadataDirectives());
    }

    public function test_it_is_case_insensitive_and_preserves_unknown_alias_tokens(): void
    {
        $expression = TorrentSearchExpression::fromQuery('Matrix RG:ntb SOURCE:web-dl Foo:Bar LANG:ENGLISH AUDIO:JAPANESE SUB:DANISH,ENGLISH');

        $this->assertSame('Matrix Foo:Bar', $expression->text);
        $this->assertSame('NTB', $expression->releaseGroup);
        $this->assertSame('WEB-DL', $expression->source);
        $this->assertSame('english', $expression->language);
        $this->assertSame('japanese', $expression->audioLanguage);
        $this->assertSame('danish,english', $expression->subtitleLanguage);
        $this->assertTrue($expression->hasMetadataDirectives());
    }

    public function test_it_parses_language_audio_and_subtitle_aliases(): void
    {
        $expression = TorrentSearchExpression::fromQuery('matrix lang:english audio:japanese sub:danish,english,german');

        $this->assertSame('matrix', $expression->text);
        $this->assertSame('english', $expression->language);
        $this->assertSame('japanese', $expression->audioLanguage);
        $this->assertSame('danish,english,german', $expression->subtitleLanguage);
        $this->assertTrue($expression->hasMetadataDirectives());
    }

    public function test_it_ignores_invalid_year_and_keeps_unknown_tokens_in_text(): void
    {
        $expression = TorrentSearchExpression::fromQuery('Docu subs:gold year:abcd year:1888');

        $this->assertSame('Docu subs:gold', $expression->text);
        $this->assertNull($expression->year);
        $this->assertFalse($expression->hasMetadataDirectives());
    }
}
