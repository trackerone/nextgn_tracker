<?php

declare(strict_types=1);

namespace App\Services\Uploads;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UploadPathGenerator
{
    public function generate(string $baseDirectory, string $extension): string
    {
        $base = trim($baseDirectory, '/');

        if ($base === '') {
            throw new InvalidArgumentException('Base directory for uploads cannot be empty.');
        }

        $normalizedExtension = ltrim(strtolower($extension), '.');

        if ($normalizedExtension === '') {
            throw new InvalidArgumentException('Upload extension cannot be empty.');
        }

        $now = CarbonImmutable::now();
        $filename = Str::uuid()->toString();

        return sprintf(
            '%s/%s/%s/%s.%s',
            $base,
            $now->format('Y'),
            $now->format('m'),
            $filename,
            $normalizedExtension
        );
    }
}
