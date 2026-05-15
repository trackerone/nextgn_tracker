<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        $plainKey = ApiKey::generateKey();

        return [
            'user_id' => User::factory(),
            'key' => ApiKey::storageKeyForPlaintext($plainKey),
            ...ApiKey::hashedAttributesForPlaintext($plainKey),
            ...ApiKey::hmacAttributesForPlaintext($plainKey),
            'label' => $this->faker->optional()->words(3, true),
            'last_used_at' => null,
        ];
    }

    public function withPlainKey(string $plainKey): self
    {
        return $this->state(fn (): array => [
            'key' => ApiKey::storageKeyForPlaintext($plainKey),
            ...ApiKey::hashedAttributesForPlaintext($plainKey),
            ...ApiKey::hmacAttributesForPlaintext($plainKey),
        ]);
    }

    public function legacyPlaintext(string $plainKey): self
    {
        return $this->state(fn (): array => [
            'key' => $plainKey,
            'key_prefix' => null,
            'key_hash' => null,
            'hmac_secret_hash' => null,
            'hmac_secret_fingerprint' => null,
            'hmac_version' => 'legacy-global',
        ]);
    }
}
