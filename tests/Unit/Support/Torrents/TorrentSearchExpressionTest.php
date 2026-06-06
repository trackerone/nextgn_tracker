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

    public function test_it_recognizes_supported_aliases_case_insensitively(): void
    {
        $expression = TorrentSearchExpression::fromQuery(
            'Matrix Rg:ntb SoUrCe:web-dl ReS:2160P LaNg:ENGLISH AuDiO:JAPANESE SuB:DANISH,ENGLISH'
        );

        $this->assertSame('Matrix', $expression->text);
        $this->assertSame('NTB', $expression->releaseGroup);
        $this->assertSame('WEB-DL', $expression->source);
        $this->assertSame('2160p', $expression->resolution);
        $this->assertSame('english', $expression->language);
        $this->assertSame('japanese', $expression->audioLanguage);
        $this->assertSame('danish,english', $expression->subtitleLanguage);
        $this->assertTrue($expression->hasMetadataDirectives());
    }

    public function test_it_preserves_comma_separated_subtitle_alias_values(): void
    {
        $expression = TorrentSearchExpression::fromQuery('matrix sub:danish,english');

        $this->assertSame('matrix', $expression->text);
        $this->assertSame('danish,english', $expression->subtitleLanguage);
        $this->assertTrue($expression->hasMetadataDirectives());
    }

    public function test_it_keeps_unknown_alias_tokens_in_text(): void
    {
        $expression = TorrentSearchExpression::fromQuery('Matrix foo:bar');

        $this->assertSame('Matrix foo:bar', $expression->text);
        $this->assertFalse($expression->hasMetadataDirectives());
    }

    public function test_it_does_not_treat_subs_as_the_subtitle_alias(): void
    {
        $expression = TorrentSearchExpression::fromQuery('Docu subs:gold');

        $this->assertSame('Docu subs:gold', $expression->text);
        $this->assertNull($expression->subtitleLanguage);
        $this->assertFalse($expression->hasMetadataDirectives());
    }

    public function test_it_ignores_invalid_year_tokens(): void
    {
        $expression = TorrentSearchExpression::fromQuery('Docu year:abcd year:1888');

        $this->assertSame('Docu', $expression->text);
        $this->assertNull($expression->year);
        $this->assertFalse($expression->hasMetadataDirectives());
    }
}
