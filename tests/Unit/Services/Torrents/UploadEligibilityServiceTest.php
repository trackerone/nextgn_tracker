<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\UploadEligibilityEvent;
use App\Models\User;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use App\Services\Torrents\UploadPreflightContext;
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

        $decision = $this->service->evaluate($user, new UploadPreflightContext(
            category: null,
            type: 'movie',
            resolution: '1080p',
            scene: false,
            duplicate: null,
            size: 2_048,
            isBanned: false,
            isDisabled: false,
            metadataComplete: null,
            infoHash: null,
            existingTorrentId: null,
        ));

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

        $decision = $this->service->evaluate($user, new UploadPreflightContext(
            category: 'Movies',
            type: 'movie',
            resolution: null,
            scene: null,
            duplicate: true,
            size: null,
            isBanned: true,
            isDisabled: false,
            metadataComplete: null,
            infoHash: null,
            existingTorrentId: null,
        ));

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
        $decision = $this->service->decide(new UploadPreflightContext(
            category: null,
            type: 'movie',
            resolution: null,
            scene: null,
            duplicate: null,
            size: null,
            isBanned: false,
            isDisabled: false,
            metadataComplete: null,
            infoHash: null,
            existingTorrentId: null,
        ));

        $this->assertTrue($decision->allowed);
        $this->assertNull($decision->reason);
        $this->assertDatabaseCount('upload_eligibility_events', 0);
        Log::shouldNotHaveReceived('info');
    }

    public function test_can_upload_remains_compatible_with_decision_allowed_flag(): void
    {
        $context = new UploadPreflightContext(
            category: null,
            type: null,
            resolution: null,
            scene: null,
            duplicate: false,
            size: null,
            isBanned: false,
            isDisabled: false,
            metadataComplete: true,
            infoHash: null,
            existingTorrentId: null,
        );

        $this->assertSame(
            $this->service->decide($context)->allowed,
            $this->service->canUpload($context),
        );
    }


    public function test_decide_prioritizes_user_disabled_before_other_failures(): void
    {
        $decision = $this->service->decide(new UploadPreflightContext(
            category: null,
            type: null,
            resolution: null,
            scene: null,
            duplicate: true,
            size: null,
            isBanned: false,
            isDisabled: true,
            metadataComplete: false,
            infoHash: null,
            existingTorrentId: null,
        ));

        $this->assertFalse($decision->allowed);
        $this->assertSame(UploadEligibilityReason::UserDisabled, $decision->reason);
    }

    public function test_decide_prioritizes_missing_metadata_before_duplicate(): void
    {
        $decision = $this->service->decide(new UploadPreflightContext(
            category: null,
            type: null,
            resolution: null,
            scene: null,
            duplicate: true,
            size: null,
            isBanned: false,
            isDisabled: false,
            metadataComplete: false,
            infoHash: null,
            existingTorrentId: null,
        ));

        $this->assertFalse($decision->allowed);
        $this->assertSame(UploadEligibilityReason::MissingMetadata, $decision->reason);
    }
    public function test_decide_denies_duplicate_torrent_with_explicit_reason(): void
    {
        $decision = $this->service->decide(new UploadPreflightContext(
            category: null,
            type: 'movie',
            resolution: null,
            scene: null,
            duplicate: true,
            size: 1_024,
            isBanned: false,
            isDisabled: false,
            metadataComplete: true,
            infoHash: 'ABC123',
            existingTorrentId: 10,
        ));

        $this->assertFalse($decision->allowed);
        $this->assertSame(UploadEligibilityReason::DuplicateTorrent, $decision->reason);
        $this->assertSame(true, $decision->context['duplicate'] ?? null);
        $this->assertSame(10, $decision->context['existing_torrent_id'] ?? null);
    }

    public function test_decide_denies_missing_metadata_with_explicit_reason(): void
    {
        $decision = $this->service->decide(new UploadPreflightContext(
            category: null,
            type: null,
            resolution: null,
            scene: null,
            duplicate: null,
            size: null,
            isBanned: false,
            isDisabled: false,
            metadataComplete: false,
            infoHash: null,
            existingTorrentId: null,
        ));

        $this->assertFalse($decision->allowed);
        $this->assertSame(UploadEligibilityReason::MissingMetadata, $decision->reason);
        $this->assertSame(false, $decision->context['metadata_complete'] ?? null);
    }
}
