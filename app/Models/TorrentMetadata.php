<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TorrentMetadata extends Model
{
    protected $table = 'torrent_metadata';

    protected $fillable = [
        'torrent_id',
        'title',
        'year',
        'type',
        'resolution',
        'source',
        'release_group',
        'imdb_id',
        'imdb_url',
        'tmdb_id',
        'tmdb_url',
        'nfo',
        'raw_name',
        'parsed_name',
        'raw_payload',
    ];

    protected $casts = [
        'torrent_id' => 'integer',
        'year' => 'integer',
        'tmdb_id' => 'integer',
        'raw_payload' => 'array',
    ];

    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }
}
