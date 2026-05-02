<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Metadata\Providers;

use App\Models\SiteSetting;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\Providers\ImdbMetadataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImdbMetadataProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_fill_only_result_for_valid_imdb_id(): void
    {
        $this->setSiteSetting('metadata.providers.imdb.enabled', 'true', 'bool');

        $provider = app(ImdbMetadataProvider::class);

        $result = $provider->lookup(new ExternalMetadataLookup('tt1234567', null, null, null, null, 'movie'));

        $this->assertTrue($result->found);
        $this->assertSame('imdb', $result->provider);
        $this->assertSame('tt1234567', $result->imdbId);
        $this->assertSame('https://www.imdb.com/title/tt1234567/', $result->externalUrl);
        $this->assertSame([
            'source' => 'imdb',
            'mode' => 'fill_only',
        ], $result->rawPayload);
    }

    public function test_lookup_skips_when_imdb_provider_is_disabled(): void
    {
        $this->setSiteSetting('metadata.providers.imdb.enabled', 'false', 'bool');

        $provider = app(ImdbMetadataProvider::class);

        $result = $provider->lookup(new ExternalMetadataLookup('tt1234567', null, null, null, null, 'movie'));

        $this->assertFalse($result->found);
        $this->assertSame('Provider disabled.', $result->error);
    }

    public function test_lookup_skips_when_imdb_id_is_invalid(): void
    {
        $this->setSiteSetting('metadata.providers.imdb.enabled', 'true', 'bool');

        $provider = app(ImdbMetadataProvider::class);

        $result = $provider->lookup(new ExternalMetadataLookup('invalid-id', null, null, null, null, 'movie'));

        $this->assertFalse($result->found);
        $this->assertSame('No IMDb identifier available.', $result->error);
    }

    private function setSiteSetting(string $key, string $value, string $type): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}
