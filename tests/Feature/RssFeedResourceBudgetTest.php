<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\RssFeedPreset;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RssFeedResourceBudgetTest extends TestCase
{
    public function test_rare_text_filter_stops_after_bounded_candidates_instead_of_scanning_entire_catalog(): void
    {
        config()->set('rss.scan_budget', 100);
        $user = $this->rssUser();
        $uploader = User::factory()->create();

        $oldMatch = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'name' => 'Very Rare Needle Match',
            'uploaded_at' => now()->subDays(5),
        ]);
        Torrent::factory()->count(110)
            ->state(['user_id' => $uploader->id])
            ->sequence(fn ($sequence): array => [
                'name' => 'Unrelated Release '.$sequence->index,
                'uploaded_at' => now()->subSeconds($sequence->index),
            ])->create();

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'torrents') && str_contains($query->sql, 'select')) {
                $queries[] = $query->sql;
            }
        });

        $response = $this->get(route('rss.feed', [
            'token' => $user->rss_token,
            'q' => 'Very Rare Needle',
        ]));

        $response->assertOk()->assertDontSee($oldMatch->name);
        self::assertCount(1, $queries, 'A restrictive RSS filter must not page through the full catalog.');
    }

    public function test_type_predicate_is_pushed_down_and_preserves_metadata_first_fallback_semantics(): void
    {
        config()->set('rss.scan_budget', 100);
        $user = $this->rssUser();
        $uploader = User::factory()->create();

        Torrent::factory()->count(110)
            ->state(['user_id' => $uploader->id, 'type' => 'tv'])
            ->sequence(fn ($sequence): array => [
                'name' => 'TV Release '.$sequence->index,
                'uploaded_at' => now()->subSeconds($sequence->index),
            ])->create();

        // No metadata row: TorrentMetadataView uses the torrent's type.
        $fallback = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'name' => 'Older Movie Without Metadata',
            'type' => 'movie',
            'uploaded_at' => now()->subDays(5),
        ]);

        // Metadata row exists: its type wins over the legacy torrent field.
        $overridden = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'name' => 'Wrongly Typed Legacy Movie',
            'type' => 'movie',
            'uploaded_at' => now()->subDays(4),
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $overridden->id,
            'type' => 'tv',
        ]);

        $response = $this->get(route('rss.feed', ['token' => $user->rss_token, 'type' => 'movie']));

        $response->assertOk()->assertSee($fallback->name)->assertDontSee($overridden->name);
        $response->assertDontSee('TV Release 0');
    }

    public function test_category_freeleech_and_year_predicates_do_not_consume_budget_on_unrelated_rows(): void
    {
        config()->set('rss.scan_budget', 100);
        $user = $this->rssUser();
        $uploader = User::factory()->create();
        $wanted = Category::factory()->create();
        $other = Category::factory()->create();

        Torrent::factory()->count(110)->state([
            'user_id' => $uploader->id,
            'category_id' => $other->id,
            'is_freeleech' => false,
        ])->create();

        $match = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'name' => 'Older Eligible Candidate',
            'category_id' => $wanted->id,
            'is_freeleech' => true,
            'uploaded_at' => now()->subDays(5),
        ]);
        TorrentMetadata::query()->create(['torrent_id' => $match->id, 'year' => 2024]);

        $response = $this->get(route('rss.feed', [
            'token' => $user->rss_token,
            'category' => $wanted->id,
            'freeleech' => 1,
            'year' => 2024,
        ]));

        $response->assertOk()->assertSee($match->name);
    }

    public function test_feed_rate_limit_is_shared_between_raw_and_saved_preset_endpoints(): void
    {
        $user = $this->rssUser();
        $preset = RssFeedPreset::factory()->create(['user_id' => $user->id, 'filters' => []]);

        for ($i = 0; $i < 29; $i++) {
            $this->get(route('rss.feed', ['token' => $user->rss_token]))->assertOk();
        }

        $this->get(route('rss.presets.feed', [
            'token' => $user->rss_token,
            'preset' => $preset->public_id,
        ]))->assertOk();

        $this->get(route('rss.feed', ['token' => $user->rss_token]))->assertStatus(429);
    }

    private function rssUser(): User
    {
        $user = User::factory()->create();
        $user->rotateRssToken();

        return $user->refresh();
    }
}
