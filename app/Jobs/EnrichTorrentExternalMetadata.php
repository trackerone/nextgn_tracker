<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Torrent;
use App\Services\Metadata\ExternalMetadataEnricher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class EnrichTorrentExternalMetadata implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $torrentId) {}

    public function handle(ExternalMetadataEnricher $enricher): void
    {
        $torrent = Torrent::query()->with(['metadata', 'externalMetadata'])->find($this->torrentId);

        if (! $torrent instanceof Torrent) {
            Log::info('External metadata enrichment skipped because torrent was not found.', [
                'torrent_id' => $this->torrentId,
            ]);

            return;
        }

        $enricher->enrich($torrent);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('External metadata enrichment failed.', [
            'torrent_id' => $this->torrentId,
            'attempts' => $this->attempts(),
            'exception' => $exception::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
