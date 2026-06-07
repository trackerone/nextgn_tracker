<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryNavigationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_discovery_named_route_generates_the_discovery_path(): void
    {
        self::assertSame('/my/discovery', route('my.discovery', [], false));
        self::assertSame(url('/my/discovery'), route('my.discovery'));
    }

    public function test_discovery_page_requires_authentication(): void
    {
        $this->get(route('my.discovery'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_reach_discovery_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertOk();
    }

    public function test_browse_discovery_teaser_targets_the_discovery_route(): void
    {
        $user = User::factory()->create();
        $discoveryUrl = route('my.discovery');

        $this->actingAs($user)
            ->get(route('torrents.index'))
            ->assertOk()
            ->assertSee('data-discovery-browse-teaser', false)
            ->assertSee('data-discovery-url="'.e($discoveryUrl).'"', false);
    }
}
