<?php

declare(strict_types=1);

namespace Tests\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RequestGuardTest extends TestCase
{
    public function test_it_sanitizes_incoming_payloads(): void
    {
        Route::post('/__test/request-guard', function (Request $request) {
            return response()->json($request->all());
        });

        $response = $this->postJson('/__test/request-guard', [
            'content' => "<script>alert('x')</script><strong>safe</strong>",
        ]);

        $response->assertOk()
            ->assertJson([
                'content' => '<strong>safe</strong>',
            ]);
    }

    public function test_it_rejects_malicious_payloads(): void
    {
        Route::post('/__test/request-guard', function (Request $request) {
            return response()->json($request->all());
        });

        $response = $this->postJson('/__test/request-guard', [
            'content' => 'javascript:alert(1)',
        ]);

        $response->assertStatus(400);
    }
}
