<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

final readonly class AnnounceResult
{
    private function __construct(
        public bool $isFailure,
        public array $payload,
    ) {}

    public static function success(array $payload): self
    {
        return new self(false, $payload);
    }

    public static function failure(string $reason): self
    {
        return new self(true, ['failure reason' => $reason]);
    }
}
