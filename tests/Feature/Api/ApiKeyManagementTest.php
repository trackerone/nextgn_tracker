<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_list_and_delete_api_keys(): void
    {
        $user = User::factory()->create();

        $createResponse = $this->actingAs($user)->postJson(route('account.api-keys.store'), [
            'label' => 'Main RSS client',
        ]);

        $createResponse->assertCreated();
        $this->assertNotEmpty($createResponse->json('key'));

        $indexResponse = $this->actingAs($user)->getJson(route('account.api-keys.index'));
        $indexResponse->assertOk();
        $indexResponse->assertJsonCount(1, 'data');

        $apiKeyId = $indexResponse->json('data.0.id');
        $this->assertNotNull($apiKeyId);

        $deleteResponse = $this->actingAs($user)->deleteJson(route('account.api-keys.destroy', ['apiKey' => $apiKeyId]));
        $deleteResponse->assertOk();
        $deleteResponse->assertJson(['status' => 'deleted']);

        $this->assertSame(0, ApiKey::query()->count());
    }

    public function test_permission_is_required(): void
    {
        $user = User::factory()->create(['role' => null]);

        $response = $this->actingAs($user)->postJson(route('account.api-keys.store'), [
            'label' => 'Denied',
        ]);

        $response->assertStatus(403);
    }
}
