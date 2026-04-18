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
        $this->assertSame(2024, $expression->year);
        $this->assertTrue($expression->hasMetadataDirectives());
    }

    public function test_it_ignores_invalid_year_and_keeps_unknown_tokens_in_text(): void
    {
        $expression = TorrentSearchExpression::fromQuery('Docu quality:gold year:abcd year:1888');

        $this->assertSame('Docu quality:gold', $expression->text);
        $this->assertNull($expression->year);
        $this->assertFalse($expression->hasMetadataDirectives());
    }
}
