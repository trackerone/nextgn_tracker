<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Metadata\DTO\ExternalMetadataResult;
use App\Services\Torrents\CanonicalTorrentMetadata;

final class ExternalMetadataEnrichmentService
{
    public function enrich(CanonicalTorrentMetadata $canonical, ?ExternalMetadataResult $external): ExternalMetadataEnrichmentOutcome
    {
        if ($external === null || ! $external->found) {
            return new ExternalMetadataEnrichmentOutcome($canonical, [], [], []);
        }

        $base = $canonical->toPersistenceArray();
        $applied = [];
        $skipped = [];
        $conflicts = [];

        $this->applyFillOnly($base, 'title', $external->title, $applied, $skipped);
        $this->applyFillOnly($base, 'year', $external->year, $applied, $skipped);
        $this->applyFillOnly($base, 'imdb_id', $external->imdbId, $applied, $skipped);
        $this->applyFillOnly($base, 'tmdb_id', $external->tmdbId, $applied, $skipped);

        if ($canonical->title !== null && $external->title !== null && $canonical->title !== $external->title) {
            $conflicts[] = sprintf('title conflict: local="%s" external="%s"', $canonical->title, $external->title);
        }

        if ($canonical->year !== null && $external->year !== null && $canonical->year !== $external->year) {
            $conflicts[] = sprintf('year conflict: local=%d external=%d', $canonical->year, $external->year);
        }

        // Descriptive fields not present in canonical persistence payload are retained in raw payload enrichment.
        $this->appendDescriptiveRawPayload($base, $external, $applied, $skipped);

        return new ExternalMetadataEnrichmentOutcome(
            metadata: CanonicalTorrentMetadata::fromArray($base),
            appliedFields: $applied,
            skippedFields: $skipped,
            conflicts: $conflicts,
        );
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  list<string>  $applied
     * @param  list<string>  $skipped
     */
    private function applyFillOnly(array &$base, string $field, mixed $externalValue, array &$applied, array &$skipped): void
    {
        if ($this->isEmpty($externalValue)) {
            $skipped[] = sprintf('%s:empty_external', $field);

            return;
        }

        if (! $this->isEmpty($base[$field] ?? null)) {
            $skipped[] = sprintf('%s:local_present', $field);

            return;
        }

        $base[$field] = $externalValue;
        $applied[] = $field;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  list<string>  $applied
     * @param  list<string>  $skipped
     */
    private function appendDescriptiveRawPayload(array &$base, ExternalMetadataResult $external, array &$applied, array &$skipped): void
    {
        $rawPayload = is_array($base['raw_payload'] ?? null) ? $base['raw_payload'] : [];

        $descriptive = [
            'overview' => $external->overview,
            'poster_url' => $external->posterUrl,
            'backdrop_url' => $external->backdropUrl,
            'genres' => $external->rawPayload['genres'] ?? null,
            'runtime' => $external->rawPayload['runtime'] ?? null,
        ];

        foreach ($descriptive as $field => $value) {
            if ($this->isEmpty($value)) {
                $skipped[] = sprintf('%s:empty_external', $field);

                continue;
            }

            if (! $this->isEmpty($rawPayload[$field] ?? null)) {
                $skipped[] = sprintf('%s:local_present', $field);

                continue;
            }

            $rawPayload[$field] = $value;
            $applied[] = $field;
        }

        $base['raw_payload'] = $rawPayload;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
