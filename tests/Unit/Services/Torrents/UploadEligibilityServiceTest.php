<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\UploadEligibilityEvent;
use App\Models\User;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class UploadEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private UploadEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
        $this->service = app(UploadEligibilityService::class);
    }

    public function test_evaluate_records_telemetry_for_allowed_upload_decision(): void
    {
        $user = User::factory()->create();

        $decision = $this->service->evaluate($user, [
            'type' => 'movie',
            'resolution' => '1080p',
            'scene' => false,
            'size' => 2_048,
        ]);

        $this->assertTrue($decision->allowed);
        $this->assertNull($decision->reason);

        $this->assertDatabaseHas('upload_eligibility_events', [
            'user_id' => $user->id,
            'allowed' => true,
            'reason' => null,
        ]);

        $event = UploadEligibilityEvent::query()->latest('id')->firstOrFail();
        $this->assertSame('movie', $event->context['type'] ?? null);
        $this->assertSame('1080p', $event->context['resolution'] ?? null);
        $this->assertSame(false, $event->context['scene'] ?? null);
        $this->assertSame(2_048, $event->context['size'] ?? null);

        Log::shouldHaveReceived('info')->once()->with(
            'tracker.upload.allowed',
            \Mockery::subset([
                'user_id' => $user->id,
                'allowed' => true,
                'reason' => null,
            ])
        );
    }

    public function test_evaluate_records_telemetry_for_denied_upload_decision(): void
    {
        $user = User::factory()->create(['is_banned' => true]);

        $decision = $this->service->evaluate($user, [
            'type' => 'movie',
            'duplicate' => true,
            'category' => 'Movies',
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UploadEligibilityReason::UserBanned, $decision->reason);

        $this->assertDatabaseHas('upload_eligibility_events', [
            'user_id' => $user->id,
            'allowed' => false,
            'reason' => UploadEligibilityReason::UserBanned->value,
        ]);

        $event = UploadEligibilityEvent::query()->latest('id')->firstOrFail();
        $this->assertSame(true, $event->context['duplicate'] ?? null);
        $this->assertSame('Movies', $event->context['category'] ?? null);
        $this->assertSame(true, $event->context['is_banned'] ?? null);

        Log::shouldHaveReceived('info')->once()->with(
            'tracker.upload.denied',
            \Mockery::subset([
                'user_id' => $user->id,
                'allowed' => false,
                'reason' => UploadEligibilityReason::UserBanned->value,
            ])
        );
    }

    public function test_decide_is_pure_and_does_not_record_telemetry(): void
    {
        $user = User::factory()->create();

        $decision = $this->service->decide($user, [
            'type' => 'movie',
        ]);

        $this->assertTrue($decision->allowed);
        $this->assertNull($decision->reason);
        $this->assertDatabaseCount('upload_eligibility_events', 0);
        Log::shouldNotHaveReceived('info');
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
