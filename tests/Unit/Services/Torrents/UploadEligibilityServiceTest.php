<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\User;
use App\Services\Torrents\UploadEligibilityDecision;
use App\Services\Torrents\UploadEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UploadEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private UploadEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UploadEligibilityService::class);
    }

    public function test_allows_eligible_user_with_reason_code(): void
    {
        $user = User::factory()->create();

        $decision = $this->service->decide($user);

        $this->assertTrue($decision->allowed);
        $this->assertSame(UploadEligibilityDecision::REASON_ELIGIBLE, $decision->reason);
    }

    public function test_denies_banned_user_with_reason_code(): void
    {
        $user = User::factory()->create(['is_banned' => true]);

        $decision = $this->service->decide($user);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UploadEligibilityDecision::REASON_USER_BANNED, $decision->reason);
    }

    public function test_denies_disabled_user_with_reason_code(): void
    {
        $user = User::factory()->create(['is_disabled' => true]);

        $decision = $this->service->decide($user);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UploadEligibilityDecision::REASON_USER_DISABLED, $decision->reason);
    }

    public function test_can_upload_remains_compatible_with_decision_allowed_flag(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            $this->service->decide($user)->allowed,
            $this->service->canUpload($user),
        );
    }
}
