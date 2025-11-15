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
        $canonical = implode("\n", ['GET', '/api/user', $timestamp, '']);
        $signature = hash_hmac('sha256', $canonical, 'test-secret');

        $response = $this->withHeaders([
            'X-Api-Key' => $apiKey->key,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => $signature,
        ])->getJson('/api/user');

        $response->assertOk();
        $this->assertSame($apiKey->user_id, $response->json('id'));
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

        $response->assertStatus(401);
    }
}
