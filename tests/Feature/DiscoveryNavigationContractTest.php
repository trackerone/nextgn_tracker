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

    public function test_authenticated_user_reaches_follows_from_old_discovery_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('my.discovery'))
            ->assertRedirect(route('my.follows'));
    }

    public function test_browse_shows_language_filters_without_discovery_teaser(): void
    {
        $user = User::factory()->create();
        $discoveryUrl = route('my.discovery');

        $this->actingAs($user)
            ->get(route('torrents.index'))
            ->assertOk()
            ->assertSee('name="language"', false)
            ->assertSee('name="audio_language"', false)
            ->assertDontSee('data-discovery-browse-teaser', false)
            ->assertDontSee('data-discovery-url="'.e($discoveryUrl).'"', false);
    }
}
