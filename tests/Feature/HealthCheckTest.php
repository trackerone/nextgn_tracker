<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health');

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('status', 'ok')
                ->etc()
            );
    }
}
