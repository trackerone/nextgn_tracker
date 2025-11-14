<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Torrent;
use RuntimeException;

class TorrentAlreadyExistsException extends RuntimeException
{
    public function __construct(public readonly Torrent $torrent)
    {
        parent::__construct('Torrent already exists.');
    }
}
