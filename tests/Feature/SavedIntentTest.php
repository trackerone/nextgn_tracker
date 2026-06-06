<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SavedIntent;
use App\Models\User;
use Tests\TestCase;

final class SavedIntentTest extends TestCase
{
    public function test_user_can_save_a_filtered_browse_intent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.saved-intents.store'), [
                'name' => 'All-language 2160p movies',
                'q' => 'matrix rg:NTB',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'BLURAY',
                'grouped' => '0',
                'unsupported' => 'discard-me',
                'user_id' => User::factory()->create()->id,
            ])
            ->assertRedirect(route('account.saved-intents.index'));

        $savedIntent = SavedIntent::query()->firstOrFail();

        self::assertSame((int) $user->id, (int) $savedIntent->user_id);
        self::assertSame('All-language 2160p movies', $savedIntent->name);
        self::assertSame([
            'q' => 'matrix rg:NTB',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'BLURAY',
            'grouped' => '0',
        ], $savedIntent->criteria);
        self::assertArrayNotHasKey('unsupported', $savedIntent->criteria);
    }

    public function test_user_can_see_own_saved_intents(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        SavedIntent::factory()->create([
            'user_id' => $user->id,
            'name' => 'My WEB view',
            'criteria' => ['source' => 'WEB-DL'],
        ]);
        SavedIntent::factory()->create([
            'user_id' => $other->id,
            'name' => 'Other private view',
            'criteria' => ['source' => 'BLURAY'],
        ]);

        $this->actingAs($user)
            ->get(route('account.saved-intents.index'))
            ->assertOk()
            ->assertSee('My WEB view')
            ->assertSee('Source:')
            ->assertSee('WEB-DL')
            ->assertDontSee('source:')
            ->assertDontSee('Other private view')
            ->assertDontSee('BLURAY');
    }

    public function test_saved_intent_page_shows_intent_hub_explanation(): void
    {
        $user = User::factory()->create();

        SavedIntent::factory()->create([
            'user_id' => $user->id,
            'name' => 'All-language WEB movies',
            'criteria' => [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'release_group' => 'NTB',
                'language' => 'English',
                'audio_language' => 'Japanese',
                'subtitle_language' => 'Spanish',
                'subtitles' => 'English, Spanish, German',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('account.saved-intents.index'))
            ->assertOk()
            ->assertSee('Saved views let you reuse the same metadata intent across browse, RSS, and watch presets.')
            ->assertSee('Search aliases:')
            ->assertSee('source:', false)
            ->assertSee('res:', false)
            ->assertSee('rg:', false)
            ->assertSee('sub:', false)
            ->assertSee('Search:')
            ->assertSee('Type:')
            ->assertSee('Resolution:')
            ->assertSee('Source:')
            ->assertSee('Release group:')
            ->assertSee('Language:')
            ->assertSee('Audio language:')
            ->assertSee('Subtitle language:')
            ->assertSee('Subtitles:')
            ->assertSee('matrix')
            ->assertSee('Japanese')
            ->assertSee('Spanish');
    }

    public function test_saved_view_actions_render_together(): void
    {
        $user = User::factory()->create();

        SavedIntent::factory()->create([
            'user_id' => $user->id,
            'name' => 'All-language RSS movies',
            'criteria' => [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'release_group' => 'NTB',
                'language' => 'English',
                'audio_language' => 'Japanese',
                'subtitle_language' => 'Spanish',
                'subtitles' => 'English, Spanish, German',
                'freeleech' => '1',
                'category_id' => '42',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('account.saved-intents.index'))
            ->assertOk()
            ->assertSee('Apply saved view')
            ->assertSee('Create watch preset')
            ->assertSee('Create RSS preset')
            ->assertSee('Delete');
    }

    public function test_empty_state_links_to_browse(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.saved-intents.index'))
            ->assertOk()
            ->assertSee('Saved views are created from browse filters, then reused here for RSS and watch presets.')
            ->assertSee('source:web-dl', false)
            ->assertSee('res:1080p', false)
            ->assertSee('rg:&lt;release-group&gt;', false)
            ->assertSee('sub:&lt;language&gt;', false)
            ->assertSee(route('torrents.index'))
            ->assertSee('Go to Browse');
    }

    public function test_saved_intent_page_shows_create_watch_preset_action_with_supported_criteria(): void
    {
        $user = User::factory()->create();

        SavedIntent::factory()->create([
            'user_id' => $user->id,
            'name' => 'All-language WEB movies',
            'criteria' => [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'release_group' => 'NTB',
                'language' => 'English',
                'audio_language' => 'Japanese',
                'subtitle_language' => 'Spanish',
                'subtitles' => 'English, Spanish, German',
                'title' => 'ignored-title',
                'grouped' => '0',
            ],
        ]);

        $expectedUrl = route('account.watch-presets.create', [
            'q' => 'matrix',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'WEB-DL',
            'release_group' => 'NTB',
            'language' => 'English',
            'audio_language' => 'Japanese',
            'subtitle_language' => 'Spanish',
            'subtitles' => 'English, Spanish, German',
        ]);

        $this->actingAs($user)
            ->get(route('account.saved-intents.index'))
            ->assertOk()
            ->assertSee('Create watch preset')
            ->assertSee($expectedUrl)
            ->assertDontSee('title=ignored-title')
            ->assertDontSee('grouped=0');
    }

    public function test_saved_intent_page_shows_create_rss_preset_action(): void
    {
        $user = User::factory()->create();

        SavedIntent::factory()->create([
            'user_id' => $user->id,
            'name' => 'RSS ready view',
            'criteria' => ['q' => 'matrix'],
        ]);

        $this->actingAs($user)
            ->get(route('account.saved-intents.index'))
            ->assertOk()
            ->assertSee('Create RSS preset');
    }

    public function test_saved_intent_rss_preset_action_includes_supported_criteria_only(): void
    {
        $user = User::factory()->create();

        SavedIntent::factory()->create([
            'user_id' => $user->id,
            'name' => 'All-language RSS movies',
            'criteria' => [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'release_group' => 'NTB',
                'language' => 'English',
                'audio_language' => 'Japanese',
                'subtitle_language' => 'Spanish',
                'subtitles' => 'English, Spanish, German',
                'freeleech' => '1',
                'category_id' => '42',
                'title' => 'ignored-title',
                'grouped' => '0',
            ],
        ]);

        $expectedUrl = route('account.rss.presets.create', [
            'q' => 'matrix',
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'WEB-DL',
            'release_group' => 'NTB',
            'language' => 'English',
            'audio_language' => 'Japanese',
            'subtitle_language' => 'Spanish',
            'subtitles' => 'English, Spanish, German',
            'freeleech' => '1',
            'category' => '42',
        ]);

        $this->actingAs($user)
            ->get(route('account.saved-intents.index'))
            ->assertOk()
            ->assertSee($expectedUrl)
            ->assertDontSee('title=ignored-title')
            ->assertDontSee('grouped=0')
            ->assertDontSee('category_id=42');
    }

    public function test_watch_preset_create_page_prefills_supported_query_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.watch-presets.create', [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'release_group' => 'NTB',
                'language' => 'English',
                'audio_language' => 'Japanese',
                'subtitle_language' => 'Spanish',
                'subtitles' => 'English, Spanish, German',
                'title' => 'ignored-title',
                'grouped' => '0',
            ]))
            ->assertOk()
            ->assertSee('value="matrix"', false)
            ->assertSee('placeholder="Try: source:web-dl res:1080p rg:<release-group> sub:<language>"', false)
            ->assertSee('Search aliases:', false)
            ->assertSee('source:', false)
            ->assertSee('res:', false)
            ->assertSee('rg:', false)
            ->assertSee('sub:', false)
            ->assertSee('value="movie"', false)
            ->assertSee('value="2160p"', false)
            ->assertSee('value="WEB-DL"', false)
            ->assertSee('value="NTB"', false)
            ->assertSee('value="English"', false)
            ->assertSee('value="Japanese"', false)
            ->assertSee('value="Spanish"', false)
            ->assertSee('value="English, Spanish, German"', false)
            ->assertDontSee('ignored-title')
            ->assertDontSee('grouped');
    }

    public function test_rss_preset_create_page_prefills_supported_query_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.rss.presets.create', [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'release_group' => 'NTB',
                'language' => 'English',
                'audio_language' => 'Japanese',
                'subtitle_language' => 'Spanish',
                'subtitles' => 'English, Spanish, German',
                'freeleech' => '1',
                'category' => '42',
                'title' => 'ignored-title',
                'grouped' => '0',
            ]))
            ->assertOk()
            ->assertSee('value="matrix"', false)
            ->assertSee('placeholder="Try: source:web-dl res:1080p rg:<release-group> sub:<language>"', false)
            ->assertSee('Search aliases:', false)
            ->assertSee('source:', false)
            ->assertSee('res:', false)
            ->assertSee('rg:', false)
            ->assertSee('sub:', false)
            ->assertSee('value="movie"', false)
            ->assertSee('value="2160p"', false)
            ->assertSee('value="WEB-DL"', false)
            ->assertSee('value="NTB"', false)
            ->assertSee('value="42"', false)
            ->assertSee('value="English"', false)
            ->assertSee('value="Japanese"', false)
            ->assertSee('value="Spanish"', false)
            ->assertSee('value="English, Spanish, German"', false)
            ->assertSee('<option value="1" selected>Yes</option>', false)
            ->assertDontSee('ignored-title')
            ->assertDontSee('grouped');
    }

    public function test_user_cannot_see_or_delete_another_users_saved_intent(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $savedIntent = SavedIntent::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('account.saved-intents.apply', ['savedIntent' => $savedIntent]))
            ->assertNotFound();

        $this->actingAs($other)
            ->delete(route('account.saved-intents.destroy', ['savedIntent' => $savedIntent]))
            ->assertNotFound();

        $this->assertDatabaseHas('saved_intents', ['id' => $savedIntent->id]);
    }

    public function test_user_can_apply_a_saved_intent_back_to_browse(): void
    {
        $user = User::factory()->create();
        $savedIntent = SavedIntent::factory()->create([
            'user_id' => $user->id,
            'criteria' => [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'grouped' => '0',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('account.saved-intents.apply', ['savedIntent' => $savedIntent]))
            ->assertRedirect(route('torrents.index', [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'grouped' => '0',
            ]));
    }

    public function test_user_can_delete_own_saved_intent(): void
    {
        $user = User::factory()->create();
        $savedIntent = SavedIntent::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('account.saved-intents.destroy', ['savedIntent' => $savedIntent]))
            ->assertRedirect(route('account.saved-intents.index'));

        $this->assertDatabaseMissing('saved_intents', ['id' => $savedIntent->id]);
    }
}
