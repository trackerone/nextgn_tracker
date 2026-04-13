<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UploadRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_upload_rejects_invalid_payload_before_eligibility_evaluation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('torrents.store'), [
            'name' => '',
            'type' => 'movie',
        ]);

        $response->assertSessionHasErrors(['name', 'torrent_file']);
        $this->assertDatabaseCount('upload_eligibility_events', 0);
    }

    public function test_api_upload_rejects_invalid_payload_before_eligibility_evaluation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.uploads.store'), [
            'name' => '',
            'type' => 'movie',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'torrent_file']);
        $this->assertDatabaseCount('upload_eligibility_events', 0);
    }
}
