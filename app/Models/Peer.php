<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Peer extends Model
{
    use HasFactory;

    protected $fillable = [
        'torrent_id',
        'user_id',
        'peer_id',
        'ip',
        'port',
        'uploaded',
        'downloaded',
        'left',
        'is_seeder',
        'client',
        'is_banned_client',
        'spoof_score',
        'last_action',
        'last_announce_at',
    ];

    protected $casts = [
        'port' => 'integer',
        'uploaded' => 'integer',
        'downloaded' => 'integer',
        'left' => 'integer',
        'is_seeder' => 'boolean',
        'is_banned_client' => 'boolean',
        'spoof_score' => 'integer',
        'last_announce_at' => 'datetime',
    ];

    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
