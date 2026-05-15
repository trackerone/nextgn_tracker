<?php

declare(strict_types=1);

namespace App\Services\Tracker;

final readonly class AnnounceIntegrityEvaluation
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $uploadedDelta,
        public int $downloadedDelta,
        public bool $isCompletionTransition,
        public array $reasons,
        public ?int $elapsedSeconds = null,
        public ?int $maxUploadedDelta = null,
        public ?int $maxDownloadedDelta = null,
    ) {}

    public function isSuspicious(): bool
    {
        return $this->reasons !== [];
    }

    public function hasReason(string $reason): bool
    {
        return in_array($reason, $this->reasons, true);
    }
}
