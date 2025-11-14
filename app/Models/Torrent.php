<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Torrent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'info_hash',
        'size',
        'files_count',
        'seeders',
        'leechers',
        'completed',
        'is_visible',
        'is_approved',
        'is_banned',
        'ban_reason',
        'freeleech',
    ];

    protected $casts = [
        'size' => 'integer',
        'files_count' => 'integer',
        'seeders' => 'integer',
        'leechers' => 'integer',
        'completed' => 'integer',
        'is_visible' => 'boolean',
        'is_approved' => 'boolean',
        'is_banned' => 'boolean',
        'freeleech' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
