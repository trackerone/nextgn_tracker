<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Jobs\EnrichTorrentExternalMetadata;
use App\Models\Torrent;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Metadata\ExternalMetadataEnricher;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TorrentMetadataSourceController extends Controller
{
    public function __invoke(Request $request, Torrent $torrent, ExternalMetadataConfig $config, SiteSettingsRepository $settings, ExternalMetadataEnricher $enricher): RedirectResponse
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);
        $type = $metadata['type'] ?? null;
        $type = ExternalMetadataConfig::mediaType(is_string($type) ? $type : null, $torrent->category?->slug);
        $options = ExternalMetadataConfig::SOURCE_OPTIONS[$type ?? ''] ?? [];
        $validated = $request->validate(['source' => ['required', 'string', Rule::in($options)]]);
        $settings->set('metadata.providers.torrent.'.$torrent->id, $validated['source'], 'string');

        $response = redirect()->route('torrents.show', $torrent);
        $source = $config->sourceFor($type, (int) $torrent->id);

        if ($source === 'none') {
            return $response->with('metadata_source_status', 'External lookup is disabled for this torrent.');
        }

        if (! $config->enrichmentEnabled()) {
            return $response->with('metadata_source_warning', 'Source saved. Turn on Enable enrichment in Metadata settings, then try again.');
        }

        if ($source === 'tmdb') {
            if (! $config->providerEnabled('tmdb') || $config->tmdbApiKey() === null) {
                return $response->with('metadata_source_warning', 'Source saved. Enable TMDb and configure its API key in Metadata settings, then try again.');
            }

            $result = $enricher->enrich($torrent);
            if ($result->enrichment_status !== 'enriched') {
                return $response->with('metadata_source_warning', 'Source saved. The TMDb lookup returned no metadata. Check the API key and the film identifiers.');
            }

            return $response->with('metadata_source_status', 'TMDb metadata updated.');
        }

        EnrichTorrentExternalMetadata::dispatch($torrent->id);

        return $response->with('metadata_source_status', 'Metadata source saved and refresh queued.');
    }
}
