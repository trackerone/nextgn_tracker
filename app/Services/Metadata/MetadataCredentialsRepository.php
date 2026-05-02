<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;

final class MetadataCredentialsRepository
{
    public function __construct(private readonly Encrypter $encrypter) {}

    public function getSecret(string $key, ?string $fallback = null): ?string
    {
        $setting = SiteSetting::query()->where('key', $key)->first();

        if (! $setting instanceof SiteSetting) {
            return $this->normalizeSecret($fallback);
        }

        try {
            return $this->normalizeSecret($this->encrypter->decrypt((string) $setting->value));
        } catch (DecryptException) {
            return $this->normalizeSecret($fallback);
        }
    }

    public function setSecret(string $key, string $secret): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->encrypter->encrypt($secret),
                'type' => 'secret',
            ],
        );
    }

    public function hasSecret(string $key, ?string $fallback = null): bool
    {
        return $this->getSecret($key, $fallback) !== null;
    }

    public function clearSecret(string $key): void
    {
        SiteSetting::query()->where('key', $key)->delete();
    }

    private function normalizeSecret(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $secret = trim($value);

        return $secret === '' ? null : $secret;
    }
}
