<?php

declare(strict_types=1);

namespace App\Support\Uploads;

final class UploadTextEncoding
{
    public static function toUtf8(string $text): string
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }

        foreach (["\xFF\xFE" => 'UTF-16LE', "\xFE\xFF" => 'UTF-16BE'] as $bom => $encoding) {
            if (str_starts_with($text, $bom)) {
                return mb_convert_encoding(substr($text, 2), 'UTF-8', $encoding);
            }
        }

        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        // DOS NFO artwork uses repeated block/box bytes. Plain legacy prose is
        // treated as Windows-1252; an unmarked legacy encoding is ambiguous.
        $artBytes = preg_match_all('/[\xB0-\xB4\xB9-\xBF\xC0-\xC5\xC8-\xCE\xD9-\xDF]/', $text);
        $encoding = $artBytes !== false && $artBytes >= 8 ? 'CP437' : 'Windows-1252';
        $converted = iconv($encoding, 'UTF-8', $text);

        return $converted !== false ? $converted : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
}
