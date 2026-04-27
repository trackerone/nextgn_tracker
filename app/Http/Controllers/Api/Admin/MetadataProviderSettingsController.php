<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateMetadataProviderSettingsRequest;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Http\JsonResponse;

final class MetadataProviderSettingsController extends Controller
{
    public function __construct(
        private readonly ExternalMetadataConfig $config,
        private readonly SiteSettingsRepository $settings,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'enrichment_enabled' => $this->config->enrichmentEnabled(),
            'auto_on_publish' => $this->config->autoOnPublishEnabled(),
            'refresh_after_days' => $this->config->refreshAfterDays(),
            'providers' => [
                'tmdb' => ['enabled' => $this->config->providerEnabled('tmdb')],
                'trakt' => ['enabled' => $this->config->providerEnabled('trakt')],
                'imdb' => ['enabled' => $this->config->providerEnabled('imdb')],
            ],
            'priority' => $this->config->providerPriority(),
        ]);
    }

    public function update(UpdateMetadataProviderSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->settings->set('metadata.enrichment.enabled', $validated['enrichment_enabled'], 'bool');
        $this->settings->set('metadata.enrichment.auto_on_publish', $validated['auto_on_publish'], 'bool');
        $this->settings->set('metadata.enrichment.refresh_after_days', $validated['refresh_after_days'], 'int');
        $this->settings->set('metadata.providers.tmdb.enabled', data_get($validated, 'providers.tmdb.enabled', false), 'bool');
        $this->settings->set('metadata.providers.trakt.enabled', data_get($validated, 'providers.trakt.enabled', false), 'bool');
        $this->settings->set('metadata.providers.imdb.enabled', data_get($validated, 'providers.imdb.enabled', false), 'bool');
        $this->settings->set('metadata.providers.priority', array_values($validated['priority']), 'json');

        return $this->show();
    }
}
