<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Torrents\CanonicalTorrentMetadata;

final readonly class ExternalMetadataEnrichmentOutcome
{
    /**
     * @param  list<string>  $appliedFields
     * @param  list<string>  $skippedFields
     * @param  list<string>  $conflicts
     */
    public function __construct(
        public CanonicalTorrentMetadata $metadata,
        public array $appliedFields,
        public array $skippedFields,
        public array $conflicts,
    ) {}
}
