<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidTorrentStatusTransitionException extends RuntimeException
{
    public static function fromStatus(string $currentStatus, string $targetStatus): self
    {
        return new self(sprintf('Cannot transition torrent status from [%s] to [%s].', $currentStatus, $targetStatus));
    }
}
