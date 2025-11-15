<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'inviter_user_id',
        'max_uses',
        'uses',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'max_uses' => 'integer',
        'uses' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasRemainingUses(): bool
    {
        return $this->uses < $this->max_uses;
    }
}
