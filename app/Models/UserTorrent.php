<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Carbon\CarbonInterface|null $completed_at
 * @property \Carbon\CarbonInterface|null $first_grab_at
 * @property \Carbon\CarbonInterface|null $last_grab_at
 */
class UserTorrent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'torrent_id',
        'uploaded',
        'downloaded',
        'completed_at',
        'last_announce_at',
        'first_grab_at',
        'last_grab_at',
    ];

    protected $casts = [
        'uploaded' => 'integer',
        'downloaded' => 'integer',
        'completed_at' => 'datetime',
        'last_announce_at' => 'datetime',
        'first_grab_at' => 'datetime',
        'last_grab_at' => 'datetime',
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
