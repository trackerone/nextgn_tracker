<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasFactory;

    private const KEY_ENVIRONMENT = 'live';

    private const PREFIX_BYTES = 4;

    private const SECRET_BYTES = 32;

    /** @var list<string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'key_prefix',
        'key_hash',
        'label',
        'last_used_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key',
        'key_hash',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateKey(): string
    {
        return sprintf(
            'ngn_%s_%s_%s',
            self::KEY_ENVIRONMENT,
            bin2hex(random_bytes(self::PREFIX_BYTES)),
            bin2hex(random_bytes(self::SECRET_BYTES)),
        );
    }

    /**
     * @return array{key_prefix: string, key_hash: string}
     */
    public static function hashedAttributesForPlaintext(string $plainKey): array
    {
        $parsed = self::parseStructuredKey($plainKey);

        if ($parsed !== null) {
            return [
                'key_prefix' => $parsed['prefix'],
                'key_hash' => self::hashSecret($parsed['secret']),
            ];
        }

        return [
            'key_prefix' => self::legacyPrefix($plainKey),
            'key_hash' => self::hashSecret($plainKey),
        ];
    }

    public static function storageKeyForPlaintext(string $plainKey): string
    {
        return 'sha256:'.hash('sha256', $plainKey);
    }

    public static function findForPlaintext(string $plainKey): ?self
    {
        $attributes = self::hashedAttributesForPlaintext($plainKey);

        /** @var iterable<self> $apiKeys */
        $apiKeys = self::query()
            ->where('key_prefix', $attributes['key_prefix'])
            ->get();

        foreach ($apiKeys as $apiKey) {
            if (is_string($apiKey->key_hash) && hash_equals($apiKey->key_hash, $attributes['key_hash'])) {
                return $apiKey;
            }
        }

        // TODO: Remove this plaintext compatibility lookup after deployed keys have
        // been migrated or rotated. Successful authentication upgrades legacy keys.
        /** @var self|null $legacyApiKey */
        $legacyApiKey = self::query()
            ->whereNull('key_hash')
            ->where('key', $plainKey)
            ->first();

        return $legacyApiKey;
    }

    public function upgradeFromPlaintextIfNeeded(string $plainKey): void
    {
        if ($this->key_hash !== null) {
            return;
        }

        $this->forceFill([
            'key' => self::storageKeyForPlaintext($plainKey),
            ...self::hashedAttributesForPlaintext($plainKey),
        ])->save();
    }

    private static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * @return array{prefix: string, secret: string}|null
     */
    private static function parseStructuredKey(string $plainKey): ?array
    {
        $matches = [];
        if (preg_match('/^ngn_[a-z]+_([a-f0-9]{8})_([a-f0-9]{64})$/', $plainKey, $matches) !== 1) {
            return null;
        }

        return [
            'prefix' => $matches[1],
            'secret' => $matches[2],
        ];
    }

    private static function legacyPrefix(string $plainKey): string
    {
        return substr(hash('sha256', $plainKey), 0, 8);
    }
}
