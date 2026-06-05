<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tracker;

use App\Models\Peer;
use App\Models\Torrent;
use App\Services\Tracker\AnnounceCreditPolicy;
use App\Tracker\Announce\AnnounceRequestData;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class AnnounceCreditPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_first_announce_does_not_credit_historical_counters(): void
    {
        $decision = app(AnnounceCreditPolicy::class)->evaluate(
            null,
            $this->request(uploaded: 10_000, downloaded: 5_000),
            $this->torrent(),
        );

        $this->assertSame(0, $decision->uploadedDelta);
        $this->assertSame(0, $decision->downloadedDelta);
        $this->assertFalse($decision->isSuspicious());
    }

    public function test_positive_deltas_within_configured_limits_are_credited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00'));
        config()->set('tracker.credit.max_upload_bytes_per_second', 100);
        config()->set('tracker.credit.max_download_bytes_per_second', 100);

        $decision = app(AnnounceCreditPolicy::class)->evaluate(
            $this->peer(uploaded: 1_000, downloaded: 2_000, lastAnnounceAt: now()->subSeconds(10)),
            $this->request(uploaded: 1_500, downloaded: 2_500),
            $this->torrent(),
        );

        $this->assertSame(500, $decision->uploadedDelta);
        $this->assertSame(500, $decision->downloadedDelta);
        $this->assertFalse($decision->isSuspicious());
    }

    public function test_positive_deltas_above_configured_limits_are_not_credited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00'));
        config()->set('tracker.credit.max_upload_bytes_per_second', 100);
        config()->set('tracker.credit.max_download_bytes_per_second', 100);

        $decision = app(AnnounceCreditPolicy::class)->evaluate(
            $this->peer(uploaded: 1_000, downloaded: 2_000, lastAnnounceAt: now()->subSeconds(10)),
            $this->request(uploaded: 3_000, downloaded: 4_000),
            $this->torrent(),
        );

        $this->assertSame(0, $decision->uploadedDelta);
        $this->assertSame(0, $decision->downloadedDelta);
        $this->assertContains('uploaded_implausible', $decision->reasons);
        $this->assertContains('downloaded_implausible', $decision->reasons);
    }

    private function peer(int $uploaded, int $downloaded, Carbon $lastAnnounceAt): Peer
    {
        return new Peer([
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
            'left' => 100,
            'last_announce_at' => $lastAnnounceAt,
        ]);
    }

    private function request(int $uploaded, int $downloaded): AnnounceRequestData
    {
        return new AnnounceRequestData(
            infoHash: str_repeat('a', 20),
            peerId: str_repeat('b', 20),
            port: 6881,
            uploaded: $uploaded,
            downloaded: $downloaded,
            left: 50,
            event: null,
            numwant: 50,
            ip: null,
        );
    }

    private function torrent(): Torrent
    {
        return new Torrent(['size_bytes' => 1_000_000]);
    }
}
