<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array<string, mixed> $filters
 * @property bool $is_enabled
 */
final class NotificationWatchPreset extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'filters',
        'is_enabled',
        'last_checked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'filters' => 'array',
        'is_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TorrentWatchNotification::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
