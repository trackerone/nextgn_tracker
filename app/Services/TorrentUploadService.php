<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TorrentUploadService
{
    public function __construct(private readonly BencodeService $bencode)
    {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function handle(UploadedFile $uploadedFile, User $user, array $attributes = []): Torrent
    {
        $payload = (string) $uploadedFile->get();
        $decoded = $this->bencode->decode($payload);

        if (! is_array($decoded) || ! isset($decoded['info']) || ! is_array($decoded['info'])) {
            throw new InvalidArgumentException('Invalid torrent: missing info dictionary.');
        }

        $info = $decoded['info'];
        $name = $this->extractName($info);
        $infoHash = Str::upper(sha1($this->bencode->encode($info)));

        $existing = Torrent::query()->where('info_hash', $infoHash)->first();

        if ($existing !== null) {
            throw new TorrentAlreadyExistsException($existing);
        }

        $size = $this->calculateSize($info);
        $filesCount = $this->calculateFilesCount($info);

        $slug = $this->generateUniqueSlug($name);

        Storage::disk('local')->put(Torrent::storagePathForHash($infoHash), $payload);

        return Torrent::create([
            'user_id' => $user->id,
            'category_id' => $attributes['category_id'] ?? null,
            'name' => $name,
            'slug' => $slug,
            'info_hash' => $infoHash,
            'size' => $size,
            'files_count' => $filesCount,
            'description' => $attributes['description'] ?? null,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'uploaded_at' => Carbon::now(),
            'is_approved' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $info
     */
    private function extractName(array $info): string
    {
        $name = $info['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException('Torrent is missing a valid name.');
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function calculateSize(array $info): int
    {
        if (isset($info['length']) && is_numeric($info['length'])) {
            return (int) $info['length'];
        }

        $files = $info['files'] ?? null;

        if (! is_array($files) || $files === []) {
            throw new InvalidArgumentException('Torrent is missing file length metadata.');
        }

        $size = 0;

        foreach ($files as $file) {
            $length = $file['length'] ?? null;

            if (! is_numeric($length)) {
                throw new InvalidArgumentException('Torrent file entry is missing length.');
            }

            $size += (int) $length;
        }

        return $size;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function calculateFilesCount(array $info): int
    {
        if (isset($info['files']) && is_array($info['files'])) {
            return count($info['files']);
        }

        return 1;
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = Str::slug('torrent-'.Str::random(8));
        }

        $slug = $base;
        $suffix = 1;

        while (Torrent::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
