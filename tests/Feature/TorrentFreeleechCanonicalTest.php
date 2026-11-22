<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserStat;
use App\Services\Tracker\DownloadEligibilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TorrentFreeleechCanonicalTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_has_only_the_canonical_freeleech_column(): void
    {
        $this->assertTrue(Schema::hasColumn('torrents', 'is_freeleech'));
        $this->assertFalse(Schema::hasColumn('torrents', 'freeleech'));
    }

    public function test_api_browse_and_details_use_the_same_freeleech_state_as_download_eligibility(): void
    {
        $viewer = User::factory()->create();
        UserStat::query()->create([
            'user_id' => $viewer->id,
            'uploaded_bytes' => 100,
            'downloaded_bytes' => 1000,
        ]);

        $free = Torrent::factory()->create([
            'name' => 'Canonical Free Release',
            'is_freeleech' => true,
        ]);
        $normal = Torrent::factory()->create([
            'name' => 'Canonical Normal Release',
            'is_freeleech' => false,
        ]);

        $policy = app(DownloadEligibilityPolicy::class);
        $this->assertSame('freeleech', $policy->checkRatio($viewer, $free)['reason']);
        $this->assertSame('ratio_too_low', $policy->checkRatio($viewer, $normal)['reason']);

        $response = $this->actingAs($viewer)->getJson(route('api.torrents.index'))->assertOk();
        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($rows->get($free->id)['freeleech']);
        $this->assertFalse($rows->get($normal->id)['freeleech']);

        $this->getJson(route('api.torrents.show', $free->id))
            ->assertOk()
            ->assertJsonPath('data.freeleech', true);

        $this->getJson(route('api.torrents.show', $normal->id))
            ->assertOk()
            ->assertJsonPath('data.freeleech', false);
    }

    public function test_rss_freeleech_filter_uses_only_the_canonical_state(): void
    {
        $member = User::factory()->create();
        $free = Torrent::factory()->create([
            'name' => 'Canonical RSS Free Release',
            'is_freeleech' => true,
        ]);
        Torrent::factory()->create([
            'name' => 'Canonical RSS Normal Release',
            'is_freeleech' => false,
        ]);

        $member->rotateRssToken();

        $this->get(route('rss.feed', [
            'token' => $member->rss_token,
            'freeleech' => '1',
        ]))
            ->assertOk()
            ->assertSee($free->name)
            ->assertDontSee('Canonical RSS Normal Release');
    }
}
