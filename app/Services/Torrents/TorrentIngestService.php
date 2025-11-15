<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\Security\SanitizationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TorrentIngestService
{
    public function __construct(
        private readonly BencodeService $bencode,
        private readonly SanitizationService $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function ingest(User $user, UploadedFile $torrentFile, array $attributes): Torrent
    {
        $payload = (string) $torrentFile->get();
        $decoded = $this->bencode->decode($payload);

        if (! is_array($decoded) || ! isset($decoded['info']) || ! is_array($decoded['info'])) {
            throw new InvalidArgumentException('Invalid torrent payload: missing info dictionary.');
        }

        $info = $decoded['info'];
        $infoHash = Str::upper(sha1($this->bencode->encode($info)));
        $existing = Torrent::query()->where('info_hash', $infoHash)->first();

        if ($existing !== null) {
            throw new TorrentAlreadyExistsException($existing);
        }

        $name = $this->sanitizeName($attributes['name'] ?? $this->extractName($info));
        $slug = $this->generateUniqueSlug($name);
        $sizeBytes = $this->calculateSizeBytes($info);
        $fileCount = $this->calculateFileCount($info);

        $torrent = new Torrent([
            'user_id' => $user->id,
            'category_id' => $attributes['category_id'] ?? null,
            'name' => $name,
            'slug' => $slug,
            'info_hash' => $infoHash,
            'size_bytes' => $sizeBytes,
            'file_count' => $fileCount,
            'type' => (string) ($attributes['type'] ?? 'other'),
            'source' => $this->sanitizeOptionalString($attributes['source'] ?? null),
            'resolution' => $this->sanitizeOptionalString($attributes['resolution'] ?? null),
            'codecs' => $this->sanitizeCodecs($attributes['codecs'] ?? []),
            'tags' => $this->sanitizeTags($attributes['tags'] ?? []),
            'description' => $this->sanitizeOptionalString($attributes['description'] ?? null),
            'nfo_text' => $this->sanitizeOptionalString($attributes['nfo_text'] ?? null),
            'imdb_id' => $this->normalizeImdbId($attributes['imdb_id'] ?? null),
            'tmdb_id' => $this->normalizeNumericId($attributes['tmdb_id'] ?? null),
            'original_filename' => $this->sanitizeOptionalString($torrentFile->getClientOriginalName()),
            'is_approved' => false,
            'uploaded_at' => Carbon::now(),
        ]);

        $torrent->save();

        Storage::disk('torrents')->put(Torrent::storagePathForHash($infoHash), $payload);

        return $torrent;
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function extractName(array $info): string
    {
        $name = $info['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('Torrent is missing a valid name.');
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function calculateSizeBytes(array $info): int
    {
        if (isset($info['length']) && is_numeric($info['length'])) {
            return (int) $info['length'];
        }

        $files = $info['files'] ?? null;

        if (! is_array($files) || $files === []) {
            throw new InvalidArgumentException('Torrent files metadata is missing.');
        }

        $total = 0;

        foreach ($files as $file) {
            $length = $file['length'] ?? null;

            if (! is_numeric($length)) {
                throw new InvalidArgumentException('Torrent file entry is missing length.');
            }

            $total += (int) $length;
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function calculateFileCount(array $info): int
    {
        if (isset($info['files']) && is_array($info['files'])) {
            return count($info['files']);
        }

        return 1;
    }

    private function sanitizeName(string $name): string
    {
        $clean = $this->sanitizer->sanitizeString($name);

        if ($clean === '') {
            throw new InvalidArgumentException('Torrent name is empty after sanitization.');
        }

        return $clean;
    }

    private function sanitizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = $this->sanitizer->sanitizeString($value);

        return $clean === '' ? null : $clean;
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, string>|null
     */
    private function sanitizeTags(array $tags): ?array
    {
        $cleaned = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $value = $this->sanitizer->sanitizeString($tag);

            if ($value === '') {
                continue;
            }

            $cleaned[] = $value;
        }

        $cleaned = array_values(array_unique($cleaned));

        return $cleaned === [] ? null : $cleaned;
    }

    /**
     * @param  array<string, string|null>  $codecs
     * @return array<string, string>|null
     */
    private function sanitizeCodecs(array $codecs): ?array
    {
        $cleaned = [];

        foreach ($codecs as $key => $value) {
            if (! is_string($key) || $value === null) {
                continue;
            }

            $cleanValue = $this->sanitizer->sanitizeString($value);

            if ($cleanValue === '') {
                continue;
            }

            $cleaned[$key] = $cleanValue;
        }

        return $cleaned === [] ? null : $cleaned;
    }

    private function normalizeImdbId(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $id = strtolower(trim($id));

        return preg_match('/^tt\d{7,8}$/', $id) === 1 ? $id : null;
    }

    private function normalizeNumericId(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $clean = preg_replace('/\D+/', '', $id) ?? '';

        return $clean === '' ? null : $clean;
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
