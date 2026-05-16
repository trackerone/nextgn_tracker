<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiAuthContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_api_route_accepts_authenticated_active_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.me.stats'))
            ->assertOk();
    }

    public function test_session_api_route_rejects_unauthenticated_user(): void
    {
        $this->getJson(route('api.me.stats'))
            ->assertUnauthorized();
    }

    public function test_session_api_route_rejects_banned_user(): void
    {
        $user = User::factory()->banned()->create();

        $this->actingAs($user)
            ->getJson(route('api.me.stats'))
            ->assertForbidden();
    }

    public function test_session_api_route_rejects_disabled_user(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)
            ->getJson(route('api.me.stats'))
            ->assertForbidden();
    }

    public function test_hmac_api_route_rejects_missing_signature(): void
    {
        $this->getJson(route('api.user'))
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_hmac_api_route_rejects_invalid_signature(): void
    {
        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();

        $this->withHeaders([
            'X-Api-Key' => $plainKey,
            'X-Api-Timestamp' => (string) now()->getTimestamp(),
            'X-Api-Signature' => 'invalid-signature',
        ])->getJson(route('api.user'))
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_hmac_api_route_accepts_valid_signature(): void
    {
        $plainKey = ApiKey::generateKey();
        $apiKey = ApiKey::factory()->withPlainKey($plainKey)->for(User::factory())->create();
        $timestamp = (string) now()->getTimestamp();

        $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson(route('api.user'))
            ->assertOk()
            ->assertJsonPath('id', $apiKey->user_id);
    }

    public function test_browser_session_does_not_bypass_hmac_api_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.user'))
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_hmac_api_route_rejects_banned_key_owner(): void
    {
        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory()->banned())->create();
        $timestamp = (string) now()->getTimestamp();

        $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson(route('api.user'))
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden.']);
    }

    public function test_hmac_api_route_rejects_disabled_key_owner(): void
    {
        $plainKey = ApiKey::generateKey();
        ApiKey::factory()->withPlainKey($plainKey)->for(User::factory()->disabled())->create();
        $timestamp = (string) now()->getTimestamp();

        $this->withHeaders($this->signedHeaders($plainKey, $timestamp))
            ->getJson(route('api.user'))
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden.']);
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $plainKey, string $timestamp): array
    {
        $canonical = implode("\n", ['GET', '/api/user', $timestamp, '']);
        $signingSecret = ApiKey::hmacSigningSecretForPlaintext($plainKey);

        $this->assertIsString($signingSecret);

        return [
            'X-Api-Key' => $plainKey,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => hash_hmac('sha256', $canonical, $signingSecret),
        ];
    }
}
