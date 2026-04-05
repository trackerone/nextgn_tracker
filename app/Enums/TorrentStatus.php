<?php

declare(strict_types=1);

namespace App\Enums;

enum TorrentStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case SoftDeleted = 'soft_deleted';

    public function isModeratable(): bool
    {
        return $this === self::Pending;
    }
}
