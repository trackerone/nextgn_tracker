<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'updated_by_user_id',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function valueForKey(string $key): ?string
    {
        return self::query()->where('key', $key)->value('value');
    }

    public static function setValue(string $key, string $value, ?int $userId = null): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by_user_id' => $userId,
            ],
        );
    }

    public static function hasOverride(string $key): bool
    {
        return self::query()->where('key', $key)->exists();
    }
}
