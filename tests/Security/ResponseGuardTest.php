<?php

declare(strict_types=1);

namespace Tests\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ResponseGuardTest extends TestCase
{
    public function test_it_sets_security_headers_and_sanitizes_html(): void
    {
        Route::get('/__test/response-guard', function () {
            return response('<div><script>alert(1)</script><iframe src="//"></iframe></div>', 200, ['Content-Type' => 'text/html']);
        });

        $response = $this->get('/__test/response-guard');

        $response->assertOk();

        $policy = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; object-src 'none'; frame-ancestors 'none'";

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Content-Security-Policy', $policy);

        $this->assertStringNotContainsString('<script', $response->getContent());
        $this->assertStringNotContainsString('<iframe', $response->getContent());
    }

    public function test_it_skips_json_responses(): void
    {
        Route::get('/__test/response-guard-json', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->getJson('/__test/response-guard-json');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }
}
