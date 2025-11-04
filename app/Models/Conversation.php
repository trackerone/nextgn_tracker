<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_a_id',
        'user_b_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (Conversation $conversation): void {
            $a = (int) $conversation->user_a_id;
            $b = (int) $conversation->user_b_id;

            if ($a === $b) {
                throw new InvalidArgumentException('Conversation participants must differ.');
            }

            if ($a > $b) {
                $conversation->user_a_id = $b;
                $conversation->user_b_id = $a;
            }
        });
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(static function (Builder $inner) use ($userId): void {
            $inner->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
        });
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function isParticipant(User $user): bool
    {
        $userId = $user->getKey();

        return $userId === (int) $this->user_a_id || $userId === (int) $this->user_b_id;
    }

    public function otherParticipantId(int $userId): int
    {
        if ((int) $this->user_a_id === $userId) {
            return (int) $this->user_b_id;
        }

        if ((int) $this->user_b_id === $userId) {
            return (int) $this->user_a_id;
        }

        throw new InvalidArgumentException('User is not part of this conversation.');
    }
}
