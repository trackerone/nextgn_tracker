<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

final class TwentyByteParamDecoder
{
    public function decode(string $value): ?string
    {
        if (preg_match('/\A[0-9a-fA-F]{40}\z/', $value) === 1) {
            $bin = hex2bin($value);

            return $bin === false ? null : $bin;
        }

        if (preg_match('/%[0-9A-Fa-f]{2}/', $value) === 1) {
            $decoded = rawurldecode($value);

            return strlen($decoded) === 20 ? $decoded : null;
        }

        if (strlen($value) === 20) {
            return $value;
        }

        return null;
    }
}
