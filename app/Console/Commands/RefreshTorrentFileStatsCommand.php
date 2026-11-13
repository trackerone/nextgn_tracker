<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Torrent;
use App\Services\BencodeService;
use App\Support\Torrents\TorrentPayloadFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class RefreshTorrentFileStatsCommand extends Command
{
    protected $signature = 'torrents:refresh-file-stats {--apply : Save corrected content counts and sizes}';

    protected $description = 'Recalculate content statistics without counting padding or changing torrent files.';

    public function handle(BencodeService $bencode): int
    {
        $failed = false;
        Torrent::query()->chunkById(100, function ($torrents) use ($bencode, &$failed): void {
            foreach ($torrents as $torrent) {
                try {
                    $payload = Storage::disk((string) config('upload.torrents.disk', 'torrents'))->get($torrent->torrentStoragePath());
                    $decoded = $bencode->decode($payload);
                    $stats = TorrentPayloadFiles::stats($decoded['info']);
                    if ($this->option('apply')) {
                        $torrent->forceFill([
                            'file_count' => $stats['count'],
                            'files_count' => $stats['count'],
                            'size_bytes' => $stats['size'],
                        ])->save();
                    }
                    $this->line(sprintf('Torrent %d: %d content files, %d bytes%s', $torrent->id, $stats['count'], $stats['size'], $this->option('apply') ? ' (saved)' : ' (preview only)'));
                } catch (Throwable) {
                    $failed = true;
                    $this->error(sprintf('Torrent %d could not be read; unchanged.', $torrent->id));
                }
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
