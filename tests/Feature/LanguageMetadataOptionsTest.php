<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Languages\LanguageMetadataOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LanguageMetadataOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_metadata_registry_includes_expected_labels_in_order(): void
    {
        self::assertSame([
            'English',
            'German',
            'French',
            'Portuguese',
            'Spanish',
            'Dutch',
            'Finnish',
            'Russian',
            'Vietnamese',
            'Chinese',
            'Italian',
            'Swedish',
            'Norwegian',
            'Norwegian Bokmål',
            'Danish',
            'Japanese',
            'Thai',
            'Korean',
            'Greek',
            'Arabic',
            'Indonesian',
            'Polish',
            'Turkish',
            'Bulgarian',
            'Hebrew',
            'Romanian',
            'Icelandic',
            'Hungarian',
            'Czech',
            'Estonian',
            'Hindi',
            'Lithuanian',
            'Latvian',
            'Malay',
            'Slovak',
            'Slovenian',
            'Tamil',
            'Telugu',
            'Ukrainian',
            'Croatian',
            'Persian',
            'Panjabi',
        ], LanguageMetadataOptions::labels());

        $labels = LanguageMetadataOptions::labels();

        $this->assertContains('English', $labels);
        $this->assertContains('Japanese', $labels);
        $this->assertContains('Spanish', $labels);
        $this->assertContains('German', $labels);
        $this->assertContains('Danish', $labels);
        $this->assertContains('Norwegian Bokmål', $labels);
        $this->assertContains('Panjabi', $labels);
    }

    public function test_upload_metadata_ui_exposes_neutral_examples_and_placeholders(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');

        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get(route('torrents.upload'));

        $response->assertOk();
        $response->assertSee('Examples: English, Japanese, Spanish, German. Use labels or short codes; multiple subtitles can be comma-separated.');
        $response->assertSee('placeholder="English or en"', false);
        $response->assertSee('placeholder="Japanese or ja"', false);
        $response->assertSee('placeholder="Spanish or es"', false);
        $response->assertDontSee('Available language options:');
    }

    public function test_no_nordic_first_helper_text_remains_in_upload_metadata_ui(): void
    {
        Storage::fake('torrents');
        Storage::fake('nfo');

        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get(route('torrents.upload'));

        $response->assertOk();
        $response->assertDontSee('Use short codes such as da, no, nb, nn, sv, fi, en.');
    }
}
