<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DiscoveryNavigationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_navigation_keeps_personal_links_under_the_username(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('home'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML((string) $response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['my.follows', 'my.watch-center', 'pm.index', 'account.rss.index'] as $route) {
            $href = route($route);
            $this->assertEquals(1, $xpath->evaluate('count(//details/nav[@aria-label="User menu"]/a[@href="'.$href.'"])'));
            $this->assertEquals(0, $xpath->evaluate('count(//nav[@aria-label="Primary navigation"]/a[@href="'.$href.'"])'));
        }
        $this->assertEquals(0, $xpath->evaluate('count(//details/nav[@aria-label="User menu"]/a[@href="'.route('my.uploads').'"])'));
        $this->assertEquals(0, $xpath->evaluate('count(//details/nav[@aria-label="User menu"]/a[@href="'.route('account.snatches').'"])'));
        $this->assertEquals(1, $xpath->evaluate('count(//nav[@aria-label="Primary navigation"]/a[@href="'.route('account.snatches').'"])'));
        $this->assertEquals(4, $xpath->evaluate('count(//nav[@aria-label="Primary navigation"]/a)'));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_UPLOADER]));
        $uploaderResponse = $this->get(route('home'))->assertOk();
        $uploaderDocument = new \DOMDocument;
        @$uploaderDocument->loadHTML((string) $uploaderResponse->getContent());
        $uploaderXpath = new \DOMXPath($uploaderDocument);
        $this->assertEquals(1, $uploaderXpath->evaluate('count(//details/nav[@aria-label="User menu"]/a[@href="'.route('my.uploads').'"])'));
        $this->assertEquals(5, $uploaderXpath->evaluate('count(//nav[@aria-label="Primary navigation"]/a)'));
    }

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
