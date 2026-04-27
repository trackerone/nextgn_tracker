<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TorrentExternalMetadata extends Model
{
    use HasFactory;

    protected $table = 'torrent_external_metadata';

    protected $fillable = [
        'torrent_id',
        'imdb_id',
        'tmdb_id',
        'trakt_id',
        'trakt_slug',
        'title',
        'original_title',
        'year',
        'media_type',
        'overview',
        'poster_path',
        'poster_url',
        'backdrop_path',
        'backdrop_url',
        'tmdb_url',
        'imdb_url',
        'trakt_url',
        'providers_payload',
        'enriched_at',
        'enrichment_status',
        'last_error',
    ];

    protected $casts = [
        'torrent_id' => 'integer',
        'year' => 'integer',
        'providers_payload' => 'array',
        'enriched_at' => 'datetime',
    ];

    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }
}
