<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HnrAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'warnings' => 'integer',
        'next_warning_at' => 'datetime',
        'decay_at' => 'datetime',
        'restricted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
