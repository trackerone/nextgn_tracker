<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Metadata;

use App\Models\SiteSetting;
use App\Services\Metadata\MetadataCredentialsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MetadataCredentialsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_secret_encrypted_and_reads_plaintext_via_repository(): void
    {
        $repository = app(MetadataCredentialsRepository::class);

        $repository->setSecret('metadata.providers.tmdb.api_key', 'top-secret-key');

        $stored = SiteSetting::query()->where('key', 'metadata.providers.tmdb.api_key')->firstOrFail();

        $this->assertNotSame('top-secret-key', $stored->value);
        $this->assertSame('secret', $stored->type);
        $this->assertSame('top-secret-key', $repository->getSecret('metadata.providers.tmdb.api_key'));
    }

    public function test_it_falls_back_to_config_when_db_secret_absent_or_invalid(): void
    {
        config()->set('metadata.tmdb.api_key', 'env-fallback-key');

        $repository = app(MetadataCredentialsRepository::class);

        $this->assertSame('env-fallback-key', $repository->getSecret('metadata.providers.tmdb.api_key', config('metadata.tmdb.api_key')));

        SiteSetting::query()->create([
            'key' => 'metadata.providers.tmdb.api_key',
            'value' => 'not-encrypted',
            'type' => 'secret',
        ]);

        $this->assertSame('env-fallback-key', $repository->getSecret('metadata.providers.tmdb.api_key', config('metadata.tmdb.api_key')));
    }

    public function test_has_and_clear_secret_behavior(): void
    {
        $repository = app(MetadataCredentialsRepository::class);

        $this->assertFalse($repository->hasSecret('metadata.providers.trakt.client_id'));

        $repository->setSecret('metadata.providers.trakt.client_id', 'client-id-secret');

        $this->assertTrue($repository->hasSecret('metadata.providers.trakt.client_id'));

        $repository->clearSecret('metadata.providers.trakt.client_id');

        $this->assertFalse($repository->hasSecret('metadata.providers.trakt.client_id'));
    }
}
