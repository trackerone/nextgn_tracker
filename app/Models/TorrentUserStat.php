<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TorrentUserStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'torrent_id',
        'uploaded_bytes',
        'downloaded_bytes',
        'seed_time_seconds',
        'leech_time_seconds',
        'times_completed',
        'first_completed_at',
        'last_completed_at',
        'last_announced_at',
    ];

    protected $casts = [
        'uploaded_bytes' => 'integer',
        'downloaded_bytes' => 'integer',
        'seed_time_seconds' => 'integer',
        'leech_time_seconds' => 'integer',
        'times_completed' => 'integer',
        'first_completed_at' => 'datetime',
        'last_completed_at' => 'datetime',
        'last_announced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }
}
