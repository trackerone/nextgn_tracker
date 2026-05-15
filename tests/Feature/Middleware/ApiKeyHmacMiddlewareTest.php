<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyHmacMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_request_allows_access(): void
    {
        $plainKey = ApiKey::generateKey();
        $apiKey = ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson('/api/user');

        $response->assertOk();
        $this->assertSame($apiKey->user_id, $response->json('id'));
    }

    public function test_key_a_secret_cannot_sign_requests_for_key_b(): void
    {
        $plainKeyA = ApiKey::generateKey();
        $plainKeyB = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKeyA)->for(User::factory())->create();
        ApiKey::factory()->withPlainKey($plainKeyB)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();
        $keyASecret = ApiKey::hmacSigningSecretForPlaintext($plainKeyA);

        $this->assertIsString($keyASecret);

        $response = $this->withHeaders($this->signedHeaders($plainKeyB, $timestamp, $keyASecret))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_invalid_api_key_is_rejected(): void
    {
        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders(ApiKey::generateKey(), $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_legacy_global_hmac_signature_format_still_allows_access(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);

        $plainKey = $this->legacyStructuredKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp, 'test-secret'))
            ->getJson('/api/user');

        $response->assertOk();
    }

    public function test_prefix_lookup_and_hash_verification_allow_access(): void
    {
        $plainKey = ApiKey::generateKey();
        $attributes = ApiKey::hashedAttributesForPlaintext($plainKey);
        $hmacAttributes = ApiKey::hmacAttributesForPlaintext($plainKey);
        $apiKey = ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $this->assertSame($attributes['key_prefix'], $apiKey->key_prefix);
        $this->assertSame($attributes['key_hash'], $apiKey->key_hash);
        $this->assertSame($hmacAttributes['hmac_secret_hash'], $apiKey->hmac_secret_hash);
        $this->assertSame('per-key', $apiKey->hmac_version);

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson('/api/user');

        $response->assertOk();
    }

    public function test_legacy_plaintext_key_is_upgraded_on_first_successful_use(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);

        $plainKey = bin2hex(random_bytes(32));
        $apiKey = ApiKey::factory()->legacyPlaintext($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp, 'test-secret'))
            ->getJson('/api/user');

        $response->assertOk();

        $apiKey->refresh();
        $this->assertNotSame($plainKey, $apiKey->key);
        $this->assertSame(ApiKey::hashedAttributesForPlaintext($plainKey)['key_prefix'], $apiKey->key_prefix);
        $this->assertSame(ApiKey::hashedAttributesForPlaintext($plainKey)['key_hash'], $apiKey->key_hash);
        $this->assertTrue($apiKey->usesLegacyGlobalHmac());
    }

    public function test_old_timestamp_is_rejected(): void
    {
        config(['security.api.allowed_time_skew_seconds' => 300]);

        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->subSeconds(301)->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_future_timestamp_is_rejected(): void
    {
        config(['security.api.allowed_time_skew_seconds' => 300]);

        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->addSeconds(301)->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_missing_timestamp_is_rejected(): void
    {
        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();
        $headers = $this->signedHeaders($plainKey, $timestamp);
        unset($headers['X-Api-Timestamp']);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_non_numeric_timestamp_is_rejected(): void
    {
        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = 'not-a-timestamp';

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders([
            'X-Api-Key' => $plainKey,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => 'invalid',
        ])->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_revoked_key_fails_authentication(): void
    {
        $plainKey = ApiKey::generateKey();
        $apiKey = ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $apiKey->delete();

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $plainKey, string $timestamp, ?string $secret = null): array
    {
        $canonical = implode("\n", ['GET', '/api/user', $timestamp, '']);
        $signingSecret = $secret ?? ApiKey::hmacSigningSecretForPlaintext($plainKey);

        $this->assertIsString($signingSecret);

        return [
            'X-Api-Key' => $plainKey,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => hash_hmac('sha256', $canonical, $signingSecret),
        ];
    }

    private function legacyStructuredKey(): string
    {
        return sprintf('ngn_live_%s_%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(32)));
    }
}
