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
        config(['security.api.hmac_secret' => 'test-secret']);

        $plainKey = ApiKey::generateKey();
        $apiKey = ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson('/api/user');

        $response->assertOk();
        $this->assertSame($apiKey->user_id, $response->json('id'));
    }

    public function test_invalid_api_key_is_rejected(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);

        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders(ApiKey::generateKey(), $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_existing_hmac_signature_format_still_allows_access(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);

        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();
        $canonical = implode("\n", ['GET', '/api/user', $timestamp, '']);

        $response = $this->withHeaders([
            'X-Api-Key' => $plainKey,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => hash_hmac('sha256', $canonical, 'test-secret'),
        ])->getJson('/api/user');

        $response->assertOk();
    }

    public function test_prefix_lookup_and_hash_verification_allow_access(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);

        $plainKey = ApiKey::generateKey();
        $attributes = ApiKey::hashedAttributesForPlaintext($plainKey);
        $apiKey = ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $this->assertSame($attributes['key_prefix'], $apiKey->key_prefix);
        $this->assertSame($attributes['key_hash'], $apiKey->key_hash);

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

        $response = $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson('/api/user');

        $response->assertOk();

        $apiKey->refresh();
        $this->assertNotSame($plainKey, $apiKey->key);
        $this->assertSame(ApiKey::hashedAttributesForPlaintext($plainKey)['key_prefix'], $apiKey->key_prefix);
        $this->assertSame(ApiKey::hashedAttributesForPlaintext($plainKey)['key_hash'], $apiKey->key_hash);
    }

    public function test_old_timestamp_is_rejected(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);
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
        config(['security.api.hmac_secret' => 'test-secret']);
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
        config(['security.api.hmac_secret' => 'test-secret']);

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
        config(['security.api.hmac_secret' => 'test-secret']);

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
        config(['security.api.hmac_secret' => 'test-secret']);

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

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $plainKey, string $timestamp): array
    {
        $canonical = implode("\n", ['GET', '/api/user', $timestamp, '']);
        $signature = hash_hmac('sha256', $canonical, 'test-secret');

        return [
            'X-Api-Key' => $plainKey,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => $signature,
        ];
    }
}
