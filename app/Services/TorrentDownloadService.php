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
        $disk = Storage::disk((string) config('upload.torrents.disk', 'torrents'));
        $relativePath = $torrent->torrentStoragePath();

        if (!$disk->exists($relativePath)) {
            throw new RuntimeException('Torrent file not found.');
        }

        $payload = $disk->get($relativePath);
        $decoded = $this->bencode->decode($payload);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid torrent payload.');
        }

        $decoded['announce'] = $this->announceUrlForUser($user);
        unset($decoded['announce-list']);

        return $this->bencode->encode($decoded);
    }

    private function announceUrlForUser(User $user): string
    {
        $config = (string) config('tracker.announce_url', '/announce/%s');
        $passkey = $user->ensurePasskey();

        if (str_contains($config, '%s')) {
            return sprintf($config, $passkey);
        }

        return rtrim($config, '/').'/'.$passkey;
    }
}
