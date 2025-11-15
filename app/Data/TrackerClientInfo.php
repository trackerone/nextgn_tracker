<?php

declare(strict_types=1);

namespace App\Data;

class TrackerClientInfo
{
    public function __construct(
        public readonly string $clientName,
        public readonly ?string $clientVersion,
        public readonly bool $isAllowed,
        public readonly bool $isBanned,
    ) {
    }
}
