<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use App\Policies\TorrentPolicy;
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
