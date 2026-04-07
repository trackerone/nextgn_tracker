<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

final readonly class AnnounceRequestData
{
    public function __construct(
        public string $infoHash,
        public string $peerId,
        public int $port,
        public int $uploaded,
        public int $downloaded,
        public int $left,
        public ?string $event,
        public int $numwant,
        public ?string $ip,
    ) {}
}
