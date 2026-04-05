<?php

declare(strict_types=1);

namespace Tests\Feature\Torrent;

use App\Enums\TorrentStatus;
use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TorrentOperationalHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_denied_torrent_details_are_hidden_and_logged(): void
    {
        $viewer = User::factory()->create();
        $torrent = Torrent::factory()->unapproved()->create();

        $this->actingAs($viewer)->get(route('torrents.show', $torrent))->assertNotFound();

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $viewer->id,
            'action' => 'torrent.access.denied_details',
        ]);
    }

    public function test_denied_torrent_download_is_hidden_and_logged(): void
    {
        $viewer = User::factory()->create();
        $torrent = Torrent::factory()->rejected()->create();

        $this->actingAs($viewer)
            ->getJson(route('api.torrents.download', $torrent))
            ->assertNotFound();

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $viewer->id,
            'action' => 'torrent.access.denied_download',
        ]);
    }

    public function test_unauthorized_moderation_attempt_is_logged(): void
    {
        $member = User::factory()->create();
        $torrent = Torrent::factory()->unapproved()->create();

        $this->actingAs($member)
            ->postJson(route('api.moderation.uploads.approve', $torrent))
            ->assertForbidden();

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $member->id,
            'action' => 'torrent.moderation.unauthorized',
        ]);
    }

    public function test_invalid_moderation_transition_is_logged(): void
    {
        $staff = User::factory()->create([
            'is_staff' => true,
        ]);

        $torrent = Torrent::factory()->create([
            'status' => TorrentStatus::Published,
            'is_approved' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($staff)
            ->postJson(route('api.moderation.uploads.approve', $torrent))
            ->assertUnprocessable();

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $staff->id,
            'action' => 'torrent.moderation.invalid_transition',
        ]);
    }

    public function test_api_torrent_download_endpoint_is_rate_limited(): void
    {
        Storage::fake('torrents');
        config()->set('security.rate_limits.torrent_download', '1,1');

        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $payload = app(BencodeService::class)->encode([
            'announce' => 'https://tracker.invalid/announce',
            'info' => ['name' => 'rate-limit-file'],
        ]);

        Storage::disk('torrents')->put($torrent->torrentStoragePath(), $payload);

        $this->actingAs($user)
            ->getJson(route('api.torrents.download', $torrent))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('api.torrents.download', $torrent))
            ->assertTooManyRequests();
    }
}
