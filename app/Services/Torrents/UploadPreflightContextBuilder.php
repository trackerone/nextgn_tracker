<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Metadata\ExternalMetadataConfig;
use App\Services\Metadata\ExternalMetadataEnrichmentService;
use Illuminate\Support\Str;

final class UploadPreflightContextBuilder implements UploadPreflightContextBuilderContract
{
    /**
     * @var array<string, ExternalMetadataProvider>
     */
    private array $providers;

    public function __construct(
        private readonly BencodeService $bencode,
        private readonly TorrentMetadataExtractor $metadataExtractor,
        private readonly UploadReleaseAdvisor $releaseAdvisor,
        private readonly ExternalMetadataConfig $externalMetadataConfig,
        private readonly ExternalMetadataEnrichmentService $externalMetadataEnrichmentService,
        iterable $providers,
    ) {
        $this->providers = [];

        foreach ($providers as $provider) {
            if (! $provider instanceof ExternalMetadataProvider) {
                continue;
            }

            $this->providers[$provider->providerKey()] = $provider;
        }
    }

    public function forUser(User $user, array $input = []): UploadPreflightContext
    {
        return $this->makeContext($user, $input, []);
    }

    public function forPayload(User $user, string $torrentPayload, array $input = []): UploadPreflightContext
    {
        $rawNfo = is_string($input['nfo_text'] ?? null) ? $input['nfo_text'] : null;
        $extractedMetadata = $this->metadataExtractor->extract($torrentPayload, $rawNfo);
        $canonicalMetadata = CanonicalTorrentMetadata::fromExtractedMetadata(
            $extractedMetadata,
            is_string($input['type'] ?? null) ? $input['type'] : null,
            is_string($input['resolution'] ?? null) ? $input['resolution'] : null,
            is_string($input['source'] ?? null) ? $input['source'] : null,
        );
        $externalMetadata = $this->lookupExternalMetadata($canonicalMetadata);
        $enrichmentOutcome = $this->externalMetadataEnrichmentService->enrich($canonicalMetadata, $externalMetadata);

        return $this->makeContext($user, $input, $this->buildPayloadContext(
            $torrentPayload,
            $extractedMetadata,
            $this->releaseAdvisor->advise($enrichmentOutcome->metadata),
            $enrichmentOutcome->appliedFields,
            $enrichmentOutcome->conflicts,
        ));
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
            releaseAdvice: is_array($payloadContext['release_advice'] ?? null) ? $payloadContext['release_advice'] : null,
            metadataEnrichmentAppliedFields: is_array($payloadContext['metadata_enrichment_applied_fields'] ?? null) ? $payloadContext['metadata_enrichment_applied_fields'] : [],
            metadataEnrichmentConflicts: is_array($payloadContext['metadata_enrichment_conflicts'] ?? null) ? $payloadContext['metadata_enrichment_conflicts'] : [],
            extractedMetadata: $payloadContext['extracted_metadata'] ?? TorrentExtractedMetadata::empty(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadContext(
        string $torrentPayload,
        TorrentExtractedMetadata $extractedMetadata,
        array $releaseAdvice,
        array $metadataEnrichmentAppliedFields = [],
        array $metadataEnrichmentConflicts = [],
    ): array {
        $decoded = $this->bencode->decode($torrentPayload);

        if (! is_array($decoded)) {
            return [
                'metadata_complete' => false,
                'release_advice' => $releaseAdvice,
                'metadata_enrichment_applied_fields' => $metadataEnrichmentAppliedFields,
                'metadata_enrichment_conflicts' => $metadataEnrichmentConflicts,
                'extracted_metadata' => $extractedMetadata,
            ];
        }

        $info = $decoded['info'] ?? null;
        if (! is_array($info)) {
            return [
                'metadata_complete' => false,
                'release_advice' => $releaseAdvice,
                'metadata_enrichment_applied_fields' => $metadataEnrichmentAppliedFields,
                'metadata_enrichment_conflicts' => $metadataEnrichmentConflicts,
                'extracted_metadata' => $extractedMetadata,
            ];
        }

        $sizeBytes = $this->extractSizeBytes($info);
        if ($sizeBytes === null) {
            return [
                'metadata_complete' => false,
                'release_advice' => $releaseAdvice,
                'metadata_enrichment_applied_fields' => $metadataEnrichmentAppliedFields,
                'metadata_enrichment_conflicts' => $metadataEnrichmentConflicts,
                'extracted_metadata' => $extractedMetadata,
            ];
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
            'release_advice' => $releaseAdvice,
            'metadata_enrichment_applied_fields' => $metadataEnrichmentAppliedFields,
            'metadata_enrichment_conflicts' => $metadataEnrichmentConflicts,
            'extracted_metadata' => $extractedMetadata,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function lookupExternalMetadata(CanonicalTorrentMetadata $canonical): ?ExternalMetadataResult
    {
        if (! $this->externalMetadataConfig->enrichmentEnabled()) {
            return null;
        }

        $lookup = new ExternalMetadataLookup(
            imdbId: $canonical->imdbId,
            tmdbId: $canonical->tmdbId !== null ? (string) $canonical->tmdbId : null,
            traktId: null,
            title: $canonical->title,
            year: $canonical->year,
            mediaType: $canonical->type,
        );

        foreach ($this->externalMetadataConfig->providerPriority() as $providerKey) {
            $provider = $this->providers[$providerKey] ?? null;
            if (! $provider instanceof ExternalMetadataProvider || ! $provider->supports($lookup)) {
                continue;
            }

            try {
                $result = $provider->lookup($lookup);
            } catch (\Throwable) {
                continue;
            }

            if ($result->found) {
                return $result;
            }
        }

        return null;
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
