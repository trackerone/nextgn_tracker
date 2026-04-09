<?php

declare(strict_types=1);

namespace App\Services\Torrents;

final readonly class UploadEligibilityDecision
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        public bool $allowed,
        public ?UploadEligibilityReason $reason,
        public array $context,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public static function allow(array $context = []): self
    {
        return new self(true, null, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function deny(UploadEligibilityReason $reason, array $context = []): self
    {
        return new self(false, $reason, $context);
    }
}
