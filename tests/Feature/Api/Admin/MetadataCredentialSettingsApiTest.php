<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class MetadataCredentialSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_status_set_and_clear_credentials_without_secret_exposure(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->getJson('/api/admin/settings/metadata/credentials/status')
            ->assertOk()
            ->assertExactJson([
                'tmdb' => ['has_api_key' => false],
                'trakt' => [
                    'has_client_id' => false,
                    'has_client_secret' => false,
                ],
            ]);

        $setResponse = $this->actingAs($admin)
            ->putJson('/api/admin/settings/metadata/credentials/tmdb', [
                'api_key' => 'tmdb-secret',
            ]);

        $setResponse->assertOk()
            ->assertExactJson(['has_api_key' => true])
            ->assertJsonMissingPath('api_key');

        $this->actingAs($admin)
            ->deleteJson('/api/admin/settings/metadata/credentials/tmdb/api_key')
            ->assertOk()
            ->assertExactJson(['has_api_key' => false]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.metadata.credentials.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.metadata.credentials.cleared']);

        $updatedAudit = AuditLog::query()->where('action', 'settings.metadata.credentials.updated')->firstOrFail();
        $clearedAudit = AuditLog::query()->where('action', 'settings.metadata.credentials.cleared')->firstOrFail();

        $this->assertArrayNotHasKey('api_key', $updatedAudit->metadata ?? []);
        $this->assertArrayNotHasKey('client_secret', $updatedAudit->metadata ?? []);
        $this->assertArrayNotHasKey('secret', $clearedAudit->metadata ?? []);
    }

    public function test_sysop_can_save_and_reload_credentials_with_the_browser_session(): void
    {
        config(['security.admin_two_factor.required' => false]);

        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->withSession([Auth::guard('web')->getName() => $sysop->getAuthIdentifier()]);
        $session = $this->app['session.store'];
        $session->save();
        $this->withCredentials()->withCookie($session->getName(), $session->getId());

        $this->putJson('/api/admin/settings/metadata/credentials/tmdb', ['api_key' => 'browser-tmdb-key'])
            ->assertOk()
            ->assertExactJson(['has_api_key' => true]);

        $this->assertDatabaseHas('site_settings', ['key' => 'metadata.providers.tmdb.api_key', 'type' => 'secret']);
        $this->assertDatabaseMissing('site_settings', ['key' => 'metadata.providers.tmdb.api_key', 'value' => 'browser-tmdb-key']);

        Auth::forgetGuards();

        $this->getJson('/api/admin/settings/metadata/credentials/status')
            ->assertOk()
            ->assertJsonPath('tmdb.has_api_key', true)
            ->assertDontSee('browser-tmdb-key');

        Auth::forgetGuards();

        $this->postJson('/api/admin/settings/metadata/providers', [
            'enrichment_enabled' => true,
            'auto_on_publish' => true,
            'refresh_after_days' => 14,
            'providers' => [
                'tmdb' => ['enabled' => true],
                'trakt' => ['enabled' => false],
                'imdb' => ['enabled' => false],
            ],
            'priority' => ['tmdb'],
        ])->assertOk()->assertJsonPath('providers.tmdb.enabled', true);
    }

    public function test_unknown_provider_or_field_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->putJson('/api/admin/settings/metadata/credentials/imdb', ['api_key' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);

        $this->actingAs($admin)
            ->deleteJson('/api/admin/settings/metadata/credentials/tmdb/client_secret')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['field']);
    }
}
