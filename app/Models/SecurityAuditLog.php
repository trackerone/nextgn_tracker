<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'context',
        'ip',
        'user_agent',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(?User $user, string $action, mixed $context = null): void
    {
        $payload = [
            'user_id' => $user?->id,
            'action' => $action,
            'context' => self::normalizeContext($context),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        self::query()->create($payload);
    }

    private static function normalizeContext(mixed $context): ?array
    {
        if ($context === null) {
            return null;
        }

        if (is_array($context)) {
            return $context;
        }

        if (is_scalar($context)) {
            return ['value' => (string) $context];
        }

        return ['value' => json_encode($context, JSON_THROW_ON_ERROR)];
    }
}
