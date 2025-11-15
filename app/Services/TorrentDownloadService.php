<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TorrentDownloadService
{
    public function __construct(private readonly BencodeService $bencode)
    {
    }

    public function buildPersonalizedPayload(Torrent $torrent, User $user): string
    {
        $disk = Storage::disk('torrents');
        $relativePath = $torrent->torrentStoragePath();

        if (! $disk->exists($relativePath)) {
            throw new RuntimeException('Torrent file not found.');
        }

        $payload = $disk->get($relativePath);
        $decoded = $this->bencode->decode($payload);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid torrent payload.');
        }

        $decoded['announce'] = $this->buildTrackerUrlForUser(
            (string) config('tracker.announce_url', '/announce/%s'),
            $user,
        );
        unset($decoded['announce-list']);

        return $this->bencode->encode($decoded);
    }

    public function buildTrackerUrlForUser(string $trackerUrl, User $user): string
    {
        $passkey = $user->ensurePasskey();

        if (str_contains($trackerUrl, '%s')) {
            return sprintf($trackerUrl, $passkey);
        }

        return rtrim($trackerUrl, '/').'/'.$passkey;
    }
}
