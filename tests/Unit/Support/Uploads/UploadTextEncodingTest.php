<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Uploads;

use App\Support\Uploads\UploadTextEncoding;
use PHPUnit\Framework\TestCase;

final class UploadTextEncodingTest extends TestCase
{
    public function test_converts_dos_artwork_without_losing_blocks_or_text(): void
    {
        $input = str_repeat("\xDB", 12)."\nSOURCE: BluRay\n";
        $result = UploadTextEncoding::toUtf8($input);

        $this->assertSame(str_repeat('█', 12)."\nSOURCE: BluRay\n", $result);
        $this->assertNotFalse(json_encode(['nfo' => $result], JSON_THROW_ON_ERROR));
    }

    public function test_preserves_utf8_and_handles_windows_text_and_bom(): void
    {
        $this->assertSame('Dansk æøå', UploadTextEncoding::toUtf8('Dansk æøå'));
        $this->assertSame('Café', UploadTextEncoding::toUtf8("Caf\xE9"));
        $this->assertSame('Title', UploadTextEncoding::toUtf8("\xEF\xBB\xBFTitle"));
        $this->assertSame('Title', UploadTextEncoding::toUtf8("\xFF\xFET\x00i\x00t\x00l\x00e\x00"));
    }
}
