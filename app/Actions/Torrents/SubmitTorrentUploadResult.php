<?php

declare(strict_types=1);

namespace App\Actions\Torrents;

use App\Models\Torrent;
use App\Services\Torrents\UploadEligibilityDecision;

final readonly class SubmitTorrentUploadResult
{
    /**
     * @param  array<string, mixed>|null  $releaseAdvice
     * @param  array{metadata_enrichment_applied_fields: list<string>, metadata_enrichment_conflicts: list<string>}  $metadataEnrichmentOutcome
     */
    private function __construct(
        public ?Torrent $torrent,
        public ?UploadEligibilityDecision $deniedDecision,
        public ?Torrent $duplicateTorrent,
        private bool $duplicate,
        public ?array $releaseAdvice,
        public array $metadataEnrichmentOutcome,
    ) {}

    /**
     * @param  array<string, mixed>|null  $releaseAdvice
     * @param  array{metadata_enrichment_applied_fields: list<string>, metadata_enrichment_conflicts: list<string>}  $metadataEnrichmentOutcome
     */
    public static function submitted(Torrent $torrent, ?array $releaseAdvice, array $metadataEnrichmentOutcome): self
    {
        return new self($torrent, null, null, false, $releaseAdvice, $metadataEnrichmentOutcome);
    }

    /**
     * @param  array<string, mixed>|null  $releaseAdvice
     * @param  array{metadata_enrichment_applied_fields: list<string>, metadata_enrichment_conflicts: list<string>}  $metadataEnrichmentOutcome
     */
    public static function duplicate(?Torrent $torrent, ?array $releaseAdvice, array $metadataEnrichmentOutcome): self
    {
        return new self(null, null, $torrent, true, $releaseAdvice, $metadataEnrichmentOutcome);
    }

    /**
     * @param  array{metadata_enrichment_applied_fields: list<string>, metadata_enrichment_conflicts: list<string>}  $metadataEnrichmentOutcome
     */
    public static function denied(UploadEligibilityDecision $decision, array $metadataEnrichmentOutcome): self
    {
        return new self(null, $decision, null, false, self::releaseAdviceFromContext($decision->context), $metadataEnrichmentOutcome);
    }

    public function isSubmitted(): bool
    {
        return $this->torrent instanceof Torrent;
    }

    public function isDuplicate(): bool
    {
        return $this->duplicate;
    }

    public function isDenied(): bool
    {
        return $this->deniedDecision instanceof UploadEligibilityDecision;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private static function releaseAdviceFromContext(array $context): ?array
    {
        return is_array($context['release_advice'] ?? null) ? $context['release_advice'] : null;
    }
}
