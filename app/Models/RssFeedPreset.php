<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $public_id
 * @property string $name
 * @property array<string, mixed> $filters
 * @property bool $is_default
 */
final class RssFeedPreset extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'public_id',
        'filters',
        'is_default',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $preset): void {
            if (! $preset instanceof RssFeedPreset) {
                return;
            }

            if ($preset->public_id === null || $preset->public_id === '') {
                $preset->public_id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
