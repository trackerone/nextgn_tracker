<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Recommendations;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use App\Support\Recommendations\RecommendationPreviewFoundationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

final class RecommendationPreviewFoundationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_foundation_returns_visible_torrents_for_recommendation_output_groups(): void
    {
        $visible = Torrent::factory()->create(['name' => 'Visible WEB 1080p']);
        $hiddenBanned = Torrent::factory()->banned()->create(['name' => 'Banned CAM']);
        $hiddenPending = Torrent::factory()->unapproved()->create(['name' => 'Pending DVDRip']);

        $this->createMetadata($visible, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'NTB',
        ]);
        $this->createMetadata($hiddenBanned, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'Hidden',
        ]);
        $this->createMetadata($hiddenPending, [
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
            'release_group' => 'Pending',
        ]);

        $previews = app(RecommendationPreviewFoundationService::class)->previewGroups();

        $this->assertCount(1, $previews);
        $this->assertSame([
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'language' => 'english',
        ], $previews[0]['group']);
        $this->assertSame([$visible->id], array_map(
            fn (array $candidate): int => (int) $candidate['torrent']->id,
            $previews[0]['candidates'],
        ));
        $this->assertSame('WEB-DL', $previews[0]['candidates'][0]['metadata']['source']);
    }

    public function test_preview_foundation_is_system_wide_without_user_specific_behavior(): void
    {
        User::factory()->count(2)->create();
        $torrent = Torrent::factory()->create();

        $this->createMetadata($torrent, [
            'source' => 'BluRay',
            'resolution' => '2160p',
            'language' => 'french',
            'release_group' => 'CtrlHD',
        ]);

        $previews = app(RecommendationPreviewFoundationService::class)->previewGroups();
        $method = new ReflectionMethod(RecommendationPreviewFoundationService::class, 'previewGroups');

        $this->assertCount(1, $previews);
        $this->assertCount(0, $method->getParameters());
        $this->assertSame($torrent->id, $previews[0]['candidates'][0]['torrent']->id);
        $this->assertSame('BluRay', $previews[0]['candidates'][0]['metadata']['source']);
    }

    /**
     * @param  array<string, string|null>  $attributes
     */
    private function createMetadata(Torrent $torrent, array $attributes): void
    {
        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            ...$attributes,
        ]);
    }
}
