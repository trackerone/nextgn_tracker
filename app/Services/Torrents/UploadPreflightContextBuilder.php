<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Support\Str;

final class UploadPreflightContextBuilder implements UploadPreflightContextBuilderContract
{
    public function __construct(
        private readonly BencodeService $bencode,
    ) {}

    public function forUser(User $user, array $input = []): UploadPreflightContext
    {
        return $this->makeContext($user, $input, []);
    }

    public function forPayload(User $user, string $torrentPayload, array $input = []): UploadPreflightContext
    {
        return $this->makeContext($user, $input, $this->buildPayloadContext($torrentPayload));
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payloadContext
     */
    private function makeContext(User $user, array $input, array $payloadContext): UploadPreflightContext
    {
        return new UploadPreflightContext(
            category: $this->asStringOrNull($input['category'] ?? null),
            type: $this->asStringOrNull($input['type'] ?? null),
            resolution: $this->asStringOrNull($input['resolution'] ?? null),
            scene: $this->asBoolOrNull($input['scene'] ?? null),
            duplicate: $this->asBoolOrNull($payloadContext['duplicate'] ?? $input['duplicate'] ?? null),
            size: $this->asIntOrNull($payloadContext['size'] ?? $input['size'] ?? null),
            isBanned: $user->isBanned(),
            isDisabled: $user->isDisabled(),
            metadataComplete: $this->asBoolOrNull($payloadContext['metadata_complete'] ?? null),
            infoHash: $this->asStringOrNull($payloadContext['info_hash'] ?? null),
            existingTorrentId: $this->asIntOrNull($payloadContext['existing_torrent_id'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadContext(string $torrentPayload): array
    {
        $decoded = $this->bencode->decode($torrentPayload);
        if (! is_array($decoded)) {
            return ['metadata_complete' => false];
        }

        $info = $decoded['info'] ?? null;
        if (! is_array($info)) {
            return ['metadata_complete' => false];
        }

        $sizeBytes = $this->extractSizeBytes($info);
        if ($sizeBytes === null) {
            return ['metadata_complete' => false];
        }

        $infoHash = Str::upper(sha1($this->bencode->encode($info)));
        $existingTorrent = Torrent::query()
            ->select(['id'])
            ->where('info_hash', $infoHash)
            ->first();

        return array_filter([
            'metadata_complete' => true,
            'size' => $sizeBytes,
            'info_hash' => $infoHash,
            'duplicate' => $existingTorrent !== null,
            'existing_torrent_id' => $existingTorrent?->getKey(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function extractSizeBytes(array $info): ?int
    {
        if (isset($info['length']) && is_numeric($info['length'])) {
            return (int) $info['length'];
        }

        $files = $info['files'] ?? null;
        if (! is_array($files) || $files === []) {
            return null;
        }

        $total = 0;

        foreach ($files as $file) {
            if (! is_array($file) || ! isset($file['length']) || ! is_numeric($file['length'])) {
                return null;
            }

            $total += (int) $file['length'];
        }

        return $total;
    }

    private function asStringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function asBoolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private function asIntOrNull(mixed $value): ?int
    {
        if (! is_int($value)) {
            return null;
        }

        return $value;
    }
}
