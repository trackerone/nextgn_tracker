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
                'name' => 'Nordic 2160p movies',
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
        self::assertSame('Nordic 2160p movies', $savedIntent->name);
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
            ->assertSee('source:')
            ->assertSee('WEB-DL')
            ->assertDontSee('Other private view')
            ->assertDontSee('BLURAY');
    }

    public function test_saved_intent_page_shows_create_watch_preset_action_with_supported_criteria(): void
    {
        $user = User::factory()->create();

        SavedIntent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nordic WEB movies',
            'criteria' => [
                'q' => 'matrix',
                'type' => 'movie',
                'resolution' => '2160p',
                'source' => 'WEB-DL',
                'release_group' => 'NTB',
                'language' => 'Danish',
                'audio_language' => 'English',
                'subtitle_language' => 'Swedish',
                'subtitles' => 'Danish, Swedish',
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
            'language' => 'Danish',
            'audio_language' => 'English',
            'subtitle_language' => 'Swedish',
            'subtitles' => 'Danish, Swedish',
        ]);

        $this->actingAs($user)
            ->get(route('account.saved-intents.index'))
            ->assertOk()
            ->assertSee('Create watch preset')
            ->assertSee($expectedUrl)
            ->assertDontSee('title=ignored-title')
            ->assertDontSee('grouped=0');
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
                'language' => 'Danish',
                'audio_language' => 'English',
                'subtitle_language' => 'Swedish',
                'subtitles' => 'Danish, Swedish',
                'title' => 'ignored-title',
                'grouped' => '0',
            ]))
            ->assertOk()
            ->assertSee('value="matrix"', false)
            ->assertSee('value="movie"', false)
            ->assertSee('value="2160p"', false)
            ->assertSee('value="WEB-DL"', false)
            ->assertSee('value="NTB"', false)
            ->assertSee('value="Danish"', false)
            ->assertSee('value="English"', false)
            ->assertSee('value="Swedish"', false)
            ->assertSee('value="Danish, Swedish"', false)
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
