<?php

declare(strict_types=1);

use App\Enums\TorrentStatus;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use App\Services\BencodeService;
use App\Services\TorrentDownloadService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$command = $argv[1] ?? '';

if ($command === 'prepare') {
    $torrentPath = $argv[2] ?? '';
    $statePath = $argv[3] ?? '';

    if ($torrentPath === '' || $statePath === '') {
        fwrite(STDERR, "Usage: php QbittorrentProbe.php prepare <torrent-path> <state-path>\n");
        exit(2);
    }

    $bencode = app(BencodeService::class);
    $info = [
        'length' => 1,
        'name' => 'nxtgn-qbittorrent-integration.bin',
        'piece length' => 16_384,
        'pieces' => sha1('x', true),
        'private' => 1,
    ];
    $infoHash = strtoupper(sha1($bencode->encode($info)));
    $placeholderAnnounce = 'http://invalid.example/announce';
    $originalPayload = $bencode->encode([
        'announce' => $placeholderAnnounce,
        'announce-list' => [
            [$placeholderAnnounce],
            ['http://backup.invalid.example/announce'],
        ],
        'comment' => 'NXTGN qBittorrent integration probe',
        'created by' => 'NXTGN integration test',
        'creation date' => time(),
        'info' => $info,
    ]);

    $user = User::query()->create([
        'name' => 'qBittorrent integration user',
        'email' => 'qbittorrent-integration@example.test',
        'password' => Str::random(64),
    ]);

    if (strlen($user->passkey) !== 64) {
        throw new RuntimeException('The integration user did not receive a 64-character passkey.');
    }

    $storagePath = Torrent::storagePathForHash($infoHash);
    $torrent = Torrent::query()->create([
        'user_id' => $user->getKey(),
        'name' => 'qBittorrent client integration fixture',
        'slug' => 'qbittorrent-client-integration-fixture',
        'info_hash' => $infoHash,
        'storage_path' => $storagePath,
        'size_bytes' => 1,
        'file_count' => 1,
        'type' => 'other',
        'seeders' => 0,
        'leechers' => 0,
        'completed' => 0,
        'is_visible' => true,
        'is_approved' => true,
        'is_banned' => false,
        'status' => TorrentStatus::Published,
        'original_filename' => 'qbittorrent-client-integration.torrent',
        'uploaded_at' => now(),
        'published_at' => now(),
    ]);

    $disk = Storage::disk((string) config('upload.torrents.disk', 'torrents'));
    if (! $disk->put($storagePath, $originalPayload)) {
        throw new RuntimeException('Unable to store the integration torrent fixture.');
    }

    $personalizedPayload = app(TorrentDownloadService::class)
        ->buildPersonalizedPayload($torrent, $user);
    $decodedPayload = $bencode->decode($personalizedPayload);
    $expectedAnnounce = sprintf((string) config('tracker.announce_url'), $user->passkey);

    if (! is_array($decodedPayload)
        || ($decodedPayload['announce'] ?? null) !== $expectedAnnounce
        || array_key_exists('announce-list', $decodedPayload)
    ) {
        throw new RuntimeException('The personalized torrent did not contain exactly the expected tracker URL.');
    }

    if (file_put_contents($torrentPath, $personalizedPayload) === false) {
        throw new RuntimeException('Unable to write the personalized torrent fixture.');
    }

    $state = json_encode([
        'info_hash' => $infoHash,
        'torrent_id' => $torrent->getKey(),
        'user_id' => $user->getKey(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

    if (file_put_contents($statePath, $state."\n") === false) {
        throw new RuntimeException('Unable to write the integration state file.');
    }

    exit(0);
}

if ($command === 'verify') {
    $statePath = $argv[2] ?? '';
    $explain = in_array('--explain', $argv, true);

    if ($statePath === '' || ! is_file($statePath)) {
        fwrite(STDERR, "Usage: php QbittorrentProbe.php verify <state-path> [--explain]\n");
        exit(2);
    }

    /** @var array{info_hash: string, torrent_id: int, user_id: int} $state */
    $state = json_decode((string) file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
    $peer = Peer::query()
        ->where('torrent_id', $state['torrent_id'])
        ->where('user_id', $state['user_id'])
        ->first();
    $userTorrent = UserTorrent::query()
        ->where('torrent_id', $state['torrent_id'])
        ->where('user_id', $state['user_id'])
        ->first();
    $torrent = Torrent::query()->find($state['torrent_id']);

    $failure = match (true) {
        ! $peer instanceof Peer => 'No peer announce has been persisted yet.',
        strlen((string) $peer->peer_id) !== 20 => 'The persisted peer ID is not 20 bytes.',
        ! str_starts_with((string) $peer->peer_id, '-qB') => 'The persisted peer did not originate from qBittorrent.',
        (int) $peer->port < 1 => 'qBittorrent announced an invalid listen port.',
        (int) $peer->uploaded !== 0 => 'The initial announce unexpectedly reported uploaded bytes.',
        (int) $peer->downloaded !== 0 => 'The initial announce unexpectedly reported downloaded bytes.',
        (int) $peer->left !== 1 => 'The qBittorrent peer is not recorded as the expected leecher.',
        (bool) $peer->is_seeder => 'The qBittorrent peer was incorrectly recorded as a seeder.',
        $peer->last_announce_at === null => 'The peer announce timestamp was not persisted.',
        ! $userTorrent instanceof UserTorrent => 'The user-torrent announce state was not persisted.',
        $userTorrent->last_announce_at === null => 'The user-torrent announce timestamp was not persisted.',
        ! $torrent instanceof Torrent => 'The integration torrent no longer exists.',
        (int) $torrent->seeders !== 0 => 'The torrent seeder count is incorrect.',
        (int) $torrent->leechers !== 1 => 'The torrent leecher count is incorrect.',
        default => null,
    };

    if ($failure !== null) {
        if ($explain) {
            fwrite(STDERR, $failure."\n");
        }

        exit(1);
    }

    fwrite(STDOUT, "qBittorrent announce persisted and swarm state verified.\n");
    exit(0);
}

fwrite(STDERR, "Expected either the prepare or verify command.\n");
exit(2);
