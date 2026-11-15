<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HnrObligation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'downloaded_bytes' => 'integer',
        'uploaded_bytes' => 'integer',
        'seed_seconds' => 'integer',
        'required_seed_seconds' => 'integer',
        'required_ratio' => 'float',
        'completed_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_counted_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'waived_at' => 'datetime',
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
