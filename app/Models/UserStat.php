<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'uploaded_bytes',
        'downloaded_bytes',
        'seed_time_seconds',
        'leech_time_seconds',
        'completed_torrents_count',
        'last_announced_at',
    ];

    protected $casts = [
        'uploaded_bytes' => 'integer',
        'downloaded_bytes' => 'integer',
        'seed_time_seconds' => 'integer',
        'leech_time_seconds' => 'integer',
        'completed_torrents_count' => 'integer',
        'last_announced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
