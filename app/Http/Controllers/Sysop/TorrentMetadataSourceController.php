<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Jobs\EnrichTorrentExternalMetadata;
use App\Models\Torrent;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TorrentMetadataSourceController extends Controller
{
    public function __invoke(Request $request, Torrent $torrent, ExternalMetadataConfig $config, SiteSettingsRepository $settings): RedirectResponse
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);
        $type = $metadata['type'] ?? null;
        $type = ExternalMetadataConfig::mediaType(is_string($type) ? $type : null, $torrent->category?->slug);
        $options = ExternalMetadataConfig::SOURCE_OPTIONS[$type ?? ''] ?? [];
        $validated = $request->validate(['source' => ['required', 'string', Rule::in($options)]]);
        $settings->set('metadata.providers.torrent.'.$torrent->id, $validated['source'], 'string');

        $message = 'Metadata source saved.';
        if ($config->enrichmentEnabled() && $config->providerPriorityFor($type, (int) $torrent->id) !== []) {
            EnrichTorrentExternalMetadata::dispatch($torrent->id);
            $message = 'Metadata source saved and refresh queued.';
        }

        return redirect()->route('torrents.show', $torrent)->with('metadata_source_status', $message);
    }
}
