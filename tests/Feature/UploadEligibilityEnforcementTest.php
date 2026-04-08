<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

use Tests\TestCase;

final class UploadEligibilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_create_is_forbidden_for_banned_or_disabled_users(): void
    {
        foreach ($this->ineligibleUsers() as $user) {
            $this->actingAs($user)
                ->get(route('torrents.upload'))
                ->assertForbidden();
        }
    }

    public function test_web_store_is_forbidden_for_banned_or_disabled_users(): void
    {
        foreach ($this->ineligibleUsers() as $user) {
            $this->actingAs($user)
                ->post(route('torrents.store'), [
                    'name' => 'Blocked upload',
                    'type' => 'movie',
                    'torrent_file' => UploadedFile::fake()->createWithContent(
                        'blocked.torrent',
                        $this->sampleTorrentPayload(),
                        'application/x-bittorrent'
                    ),
                ])
                ->assertForbidden();
        }
    }

    public function test_api_store_is_forbidden_for_banned_or_disabled_users(): void
    {
        foreach ($this->ineligibleUsers() as $user) {
            $this->actingAs($user)
                ->postJson(route('api.uploads.store'), [
                    'name' => 'Blocked API upload',
                    'type' => 'movie',
                    'torrent_file' => UploadedFile::fake()->createWithContent(
                        'blocked-api.torrent',
                        $this->sampleTorrentPayload(),
                        'application/x-bittorrent'
                    ),
                ])
                ->assertForbidden();
        }
    }

    /**
     * @return list<User>
     */
    private function ineligibleUsers(): array
    {
        return [
            User::factory()->create(['is_banned' => true]),
            User::factory()->create(['is_disabled' => true]),
        ];
    }

    private function sampleTorrentPayload(): string
    {
        return app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => 'blocked-upload',
                'piece length' => 16384,
                'length' => 1024,
                'pieces' => str_repeat('a', 20),
            ],
        ]);
    }
}
