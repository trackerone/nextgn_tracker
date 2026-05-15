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

        $apiKey = ApiKey::factory()->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($apiKey, $timestamp))
            ->getJson('/api/user');

        $response->assertOk();
        $this->assertSame($apiKey->user_id, $response->json('id'));
    }

    public function test_old_timestamp_is_rejected(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);
        config(['security.api.allowed_time_skew_seconds' => 300]);

        $apiKey = ApiKey::factory()->for(User::factory())->create();
        $timestamp = (string) now()->subSeconds(301)->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($apiKey, $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_future_timestamp_is_rejected(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);
        config(['security.api.allowed_time_skew_seconds' => 300]);

        $apiKey = ApiKey::factory()->for(User::factory())->create();
        $timestamp = (string) now()->addSeconds(301)->getTimestamp();

        $response = $this->withHeaders($this->signedHeaders($apiKey, $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_missing_timestamp_is_rejected(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);

        $apiKey = ApiKey::factory()->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();
        $headers = $this->signedHeaders($apiKey, $timestamp);
        unset($headers['X-Api-Timestamp']);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_non_numeric_timestamp_is_rejected(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);

        $apiKey = ApiKey::factory()->for(User::factory())->create();
        $timestamp = 'not-a-timestamp';

        $response = $this->withHeaders($this->signedHeaders($apiKey, $timestamp))
            ->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['security.api.hmac_secret' => 'test-secret']);

        $apiKey = ApiKey::factory()->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $response = $this->withHeaders([
            'X-Api-Key' => $apiKey->key,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => 'invalid',
        ])->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized.']);
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(ApiKey $apiKey, string $timestamp): array
    {
        $canonical = implode("\n", ['GET', '/api/user', $timestamp, '']);
        $signature = hash_hmac('sha256', $canonical, 'test-secret');

        return [
            'X-Api-Key' => (string) $apiKey->key,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => $signature,
        ];
    }
}
