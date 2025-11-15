<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Torrent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'slug',
        'info_hash',
        'size_bytes',
        'file_count',
        'type',
        'source',
        'resolution',
        'codecs',
        'tags',
        'description',
        'nfo_text',
        'imdb_id',
        'tmdb_id',
        'seeders',
        'leechers',
        'completed',
        'is_visible',
        'is_approved',
        'is_banned',
        'ban_reason',
        'freeleech',
        'original_filename',
        'uploaded_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'file_count' => 'integer',
        'seeders' => 'integer',
        'leechers' => 'integer',
        'completed' => 'integer',
        'is_visible' => 'boolean',
        'is_approved' => 'boolean',
        'is_banned' => 'boolean',
        'freeleech' => 'boolean',
        'category_id' => 'integer',
        'codecs' => 'array',
        'tags' => 'array',
        'uploaded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isVisible(): bool
    {
        return (bool) $this->is_visible;
    }

    public function isApproved(): bool
    {
        return (bool) $this->is_approved;
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }

    public function peers(): HasMany
    {
        return $this->hasMany(Peer::class);
    }

    public function userTorrents(): HasMany
    {
        return $this->hasMany(UserTorrent::class);
    }

    public static function storagePathForHash(string $infoHash): string
    {
        return 'torrents/'.strtoupper($infoHash).'.torrent';
    }

    public function torrentStoragePath(): string
    {
        return self::storagePathForHash($this->info_hash);
    }

    public function torrentFilePath(): string
    {
        return Storage::disk('torrents')->path($this->torrentStoragePath());
    }

    public function hasTorrentFile(): bool
    {
        return Storage::disk('torrents')->exists($this->torrentStoragePath());
    }
}
