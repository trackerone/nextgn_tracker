<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_route_names_generate_the_expected_paths(): void
    {
        $this->assertSame('/api/discovery/metadata', route('api.discovery.metadata', [], false));
        $this->assertSame('/api/discovery/trending', route('api.discovery.trending', [], false));
        $this->assertSame('/api/discovery/popular', route('api.discovery.popular', [], false));
    }

    public function test_authenticated_users_can_get_each_discovery_endpoint(): void
    {
        $user = User::factory()->create();

        foreach (['api.discovery.metadata', 'api.discovery.trending', 'api.discovery.popular'] as $routeName) {
            $this->actingAs($user)
                ->getJson(route($routeName))
                ->assertOk();
        }
    }

    public function test_unauthenticated_users_are_rejected_from_each_discovery_endpoint(): void
    {
        foreach (['api.discovery.metadata', 'api.discovery.trending', 'api.discovery.popular'] as $routeName) {
            $this->getJson(route($routeName))
                ->assertUnauthorized();
        }
    }

    public function test_discovery_endpoints_reject_non_get_methods(): void
    {
        $user = User::factory()->create();

        foreach (['api.discovery.metadata', 'api.discovery.trending', 'api.discovery.popular'] as $routeName) {
            foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $this->actingAs($user)
                    ->json($method, route($routeName))
                    ->assertStatus(405);
            }
        }
    }
}
