<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TorrentFollow extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'normalized_title',
        'type',
        'resolution',
        'source',
        'year',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

