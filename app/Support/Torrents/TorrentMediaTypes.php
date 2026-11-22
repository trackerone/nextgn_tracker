<?php

declare(strict_types=1);

namespace App\Support\Torrents;

final class TorrentMediaTypes
{
    /** @var list<string> */
    public const VALUES = ['movie', 'tv', 'music', 'game', 'software', 'book', 'other'];

    /** @var array<string, string> */
    public const LABELS = [
        'movie' => 'Movie',
        'tv' => 'TV',
        'music' => 'Music',
        'game' => 'Game',
        'software' => 'Software',
        'book' => 'Book',
        'other' => 'Other',
    ];
}
