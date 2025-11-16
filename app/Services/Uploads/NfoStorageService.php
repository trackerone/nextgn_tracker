<?php

declare(strict_types=1);

namespace App\Services\Uploads;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class NfoStorageService
{
    public function __construct(
        private readonly UploadPathGenerator $pathGenerator,
    )
    {
    }

    public function store(?string $contents): ?string
    {
        if ($contents === null) {
            return null;
        }

        $trimmed = trim($contents);

        if ($trimmed === '') {
            return null;
        }

        $directory = (string) config('upload.nfo.directory', 'nfo');
        $disk = (string) config('upload.nfo.disk', 'nfo');
        $path = $this->pathGenerator->generate($directory, 'nfo');

        $stored = Storage::disk($disk)->put($path, $trimmed);

        if ($stored === false) {
            throw new RuntimeException('Unable to persist NFO payload.');
        }

        return $path;
    }
}
