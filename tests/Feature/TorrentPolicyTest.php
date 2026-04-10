<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use App\Policies\TorrentPolicy;
use App\Services\Torrents\DownloadEligibilityDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TorrentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(TorrentPolicy::class);
    }

    public function test_view_policy(): void
    {
        $uploader = User::factory()->create();
        $otherUser = User::factory()->create();
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);

        $approved = Torrent::factory()->create();
        $pending = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'status' => Torrent::STATUS_PENDING,
        ]);
        $rejected = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'status' => Torrent::STATUS_REJECTED,
        ]);

        $this->assertTrue($this->policy->view($otherUser, $approved)->allowed());
        $this->assertTrue($this->policy->view($otherUser, $pending)->denied());
        $this->assertTrue($this->policy->view($otherUser, $rejected)->denied());
        $this->assertTrue($this->policy->view($uploader, $pending)->allowed());
        $this->assertTrue($this->policy->view($uploader, $rejected)->allowed());
        $this->assertTrue($this->policy->view($staff, $pending)->allowed());
    }

    public function test_download_policy(): void
    {
        $uploader = User::factory()->create();
        $otherUser = User::factory()->create();
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);

        $approved = Torrent::factory()->create();
        $pending = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'status' => Torrent::STATUS_PENDING,
        ]);
        $rejected = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'status' => Torrent::STATUS_REJECTED,
        ]);

        $this->assertTrue($this->policy->download($otherUser, $approved)->allowed());
        $this->assertTrue($this->policy->download($otherUser, $pending)->denied());
        $this->assertTrue($this->policy->download($otherUser, $rejected)->denied());
        $this->assertTrue($this->policy->download($uploader, $pending)->allowed());
        $this->assertTrue($this->policy->download($staff, $pending)->allowed());

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $otherUser->id,
            'action' => 'torrent.download.eligibility',
        ]);

        $eligibilityLog = SecurityAuditLog::query()
            ->where('user_id', $otherUser->id)
            ->where('action', 'torrent.download.eligibility')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            DownloadEligibilityDecision::REASON_NOT_ELIGIBLE,
            $eligibilityLog->context['reason'] ?? null
        );
    }

    public function test_download_policy_telemetry_is_not_emitted_for_allowed_paths(): void
    {
        $uploader = User::factory()->create();
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $approved = Torrent::factory()->create();
        $pending = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'status' => Torrent::STATUS_PENDING,
        ]);

        $this->assertTrue($this->policy->download($uploader, $pending)->allowed());
        $this->assertTrue($this->policy->download($staff, $pending)->allowed());
        $this->assertTrue($this->policy->download(User::factory()->create(), $approved)->allowed());

        $this->assertDatabaseCount('security_audit_logs', 0);
    }

    public function test_create_policy_decides_without_recording_upload_telemetry(): void
    {
        $user = User::factory()->banned()->create();

        $this->assertFalse($this->policy->create($user));

        $this->assertDatabaseCount('upload_eligibility_events', 0);
    }

    public function test_create_policy_does_not_duplicate_upload_telemetry_in_security_audit_log(): void
    {
        $user = User::factory()->disabled()->create();

        $this->assertFalse($this->policy->create($user));

        $this->assertDatabaseMissing('security_audit_logs', [
            'user_id' => $user->id,
            'action' => 'torrent.upload.eligibility',
        ]);
    }

    public function test_moderation_policy_methods(): void
    {
        $member = User::factory()->create();
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
            'is_approved' => false,
        ]);

        $this->assertFalse($this->policy->viewModerationListings($member));
        $this->assertFalse($this->policy->viewModerationItem($member, $torrent));
        $this->assertFalse($this->policy->publish($member, $torrent));
        $this->assertFalse($this->policy->reject($member, $torrent));

        $this->assertTrue($this->policy->viewModerationListings($staff));
        $this->assertTrue($this->policy->viewModerationItem($staff, $torrent));
        $this->assertTrue($this->policy->publish($staff, $torrent));
        $this->assertTrue($this->policy->reject($staff, $torrent));
    }

    public function test_update_delete_and_moderate_policy(): void
    {
        $uploader = User::factory()->create();
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $torrent = Torrent::factory()->create([
            'user_id' => $uploader->id,
        ]);
        $softDeleted = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'status' => Torrent::STATUS_SOFT_DELETED,
        ]);

        $this->assertTrue($this->policy->update($uploader, $torrent));
        $this->assertFalse($this->policy->update($uploader, $softDeleted));
        $this->assertTrue($this->policy->update($staff, $torrent));
        $this->assertFalse($this->policy->delete($uploader, $torrent));
        $this->assertTrue($this->policy->delete($staff, $torrent));
        $this->assertTrue($this->policy->moderate($staff, $torrent));
    }
}
