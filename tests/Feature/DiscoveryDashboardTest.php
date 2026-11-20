<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

final class DiscoveryDashboardTest extends TestCase
{
    public function test_members_cannot_open_staff_discovery_pages_or_read_their_data(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['admin.discovery', 'sysop.operations.discovery', 'api.discovery.operations-review-queue', 'api.discovery.health', 'api.recommendations.engine'] as $route) {
            $this->getJson(route($route))->assertForbidden();
        }
    }

    public function test_admin_can_open_release_review_and_sysop_can_open_diagnostics(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->get(route('admin.discovery'))->assertOk()->assertSee('data-discovery-operations-command-center', false);
        $this->get(route('sysop.operations.discovery'))->assertForbidden();
        $this->getJson(route('api.recommendations.engine'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SYSOP]));
        $this->get(route('sysop.operations.discovery'))->assertOk()->assertSee('data-discovery-health', false);
    }
}
