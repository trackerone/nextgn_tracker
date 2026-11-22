<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\Api\UploadSubmissionRequest;
use App\Http\Requests\Web\AnalyseTorrentUploadRequest;
use App\Http\Requests\Web\TorrentUploadStoreRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\BencodeService;
use App\Services\Settings\SiteSettingsRepository;
use App\Services\Settings\UploadLimits;
use App\Support\Uploads\TorrentUploadRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class SysopUploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_limits_default_to_safe_configured_values(): void
    {
        $this->assertSame([
            'torrent_max_kb' => 1024,
            'nfo_max_kb' => 256,
            'nfo_max_characters' => 262144,
        ], app(UploadLimits::class)->values());

        $this->assertContains('max:1024', TorrentUploadRules::rules()['torrent_file']);
        $this->assertContains('max:256', TorrentUploadRules::rules()['nfo_file']);
        $this->assertContains('max:262144', TorrentUploadRules::rules()['nfo_text']);
    }

    public function test_only_sysop_can_view_or_change_upload_limits(): void
    {
        $this->get(route('sysop.upload-settings.edit'))->assertRedirect(route('login'));
        $this->patch(route('sysop.upload-settings.update'), $this->payload())->assertRedirect(route('login'));

        foreach ([User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN] as $role) {
            $member = User::factory()->create(['role' => $role]);
            $this->actingAs($member)->get(route('sysop.upload-settings.edit'))->assertForbidden();
            $this->patch(route('sysop.upload-settings.update'), $this->payload())->assertForbidden();
        }

        $this->assertDatabaseMissing('site_settings', ['key' => UploadLimits::DEFINITIONS['torrent_max_kb']['key']]);
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)->get(route('sysop.upload-settings.edit'))
            ->assertOk()
            ->assertSee('File and NFO limits')
            ->assertSee('post_max_size');
    }

    public function test_sysop_changes_are_audited_and_all_three_upload_entry_points_consume_them(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop)
            ->patch(route('sysop.upload-settings.update'), $this->payload())
            ->assertRedirect(route('sysop.upload-settings.edit'));

        $this->assertDatabaseHas('site_settings', [
            'key' => UploadLimits::DEFINITIONS['torrent_max_kb']['key'],
            'value' => '64',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'key' => UploadLimits::DEFINITIONS['nfo_max_kb']['key'],
            'value' => '32',
        ]);

        $audit = AuditLog::query()->where('action', 'sysop.upload_limits.updated')->firstOrFail();
        $this->assertSame($sysop->id, $audit->user_id);
        $this->assertSame(1024, $audit->metadata['previous']['torrent_max_kb']);
        $this->assertSame(64, $audit->metadata['values']['torrent_max_kb']);
        $this->assertSame('Restrict the size of torrent metadata files.', $audit->metadata['audit_reason']);

        $this->assertSame([
            'torrent_max_kb' => 64,
            'nfo_max_kb' => 32,
            'nfo_max_characters' => 1024,
        ], app(UploadLimits::class)->values());

        foreach ([
            app(TorrentUploadStoreRequest::class)->rules(),
            app(UploadSubmissionRequest::class)->rules(),
            app(AnalyseTorrentUploadRequest::class)->rules(),
        ] as $rules) {
            $this->assertContains('max:64', $rules['torrent_file']);
            $this->assertContains('max:32', $rules['nfo_file']);
            $this->assertContains('max:1024', $rules['nfo_text']);
            $this->assertContains('extensions:torrent', $rules['torrent_file']);
            $this->assertContains('mimetypes:application/x-bittorrent', $rules['torrent_file']);
        }

        $uploader = User::factory()->create(['role' => User::ROLE_UPLOADER]);
        $this->actingAs($uploader)->get(route('torrents.upload'))
            ->assertOk()
            ->assertSee('Maximum 64 KB')
            ->assertSee('Maximum 32 KB')
            ->assertSee('Maximum 1,024 characters');

        $large = $this->torrentFileWithBytes(70 * 1024);
        $this->from(route('torrents.upload'))->post(route('torrents.store'), [
            'name' => 'Oversized Torrent',
            'type' => 'movie',
            'torrent_file' => $large,
        ])->assertSessionHasErrors('torrent_file');

        $this->postJson(route('api.uploads.store'), [
            'name' => 'Oversized API Torrent',
            'type' => 'movie',
            'torrent_file' => $this->torrentFileWithBytes(70 * 1024),
        ])->assertUnprocessable()->assertJsonValidationErrors('torrent_file');

        $this->postJson(route('torrents.upload.analyse'), [
            'torrent_file' => $this->torrentFileWithBytes(70 * 1024),
        ])->assertUnprocessable()->assertJsonValidationErrors('torrent_file');
    }

    public function test_inline_nfo_length_is_enforced_by_the_shared_upload_validator(): void
    {
        app(SiteSettingsRepository::class)->set(
            UploadLimits::DEFINITIONS['nfo_max_characters']['key'],
            1024,
            'int'
        );

        $uploader = User::factory()->create(['role' => User::ROLE_UPLOADER]);
        $this->actingAs($uploader)->post(route('torrents.store'), [
            'name' => 'Oversized NFO',
            'type' => 'movie',
            'nfo_text' => str_repeat('X', 1025),
            'torrent_file' => $this->torrentFileWithBytes(512),
        ])->assertSessionHasErrors('nfo_text');

        $this->postJson(route('api.uploads.store'), [
            'name' => 'Oversized API NFO',
            'type' => 'movie',
            'nfo_text' => str_repeat('X', 1025),
            'torrent_file' => $this->torrentFileWithBytes(512),
        ])->assertUnprocessable()->assertJsonValidationErrors('nfo_text');
    }

    public function test_invalid_values_and_blank_audit_reason_do_not_write_settings_or_audit(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop);

        foreach ([
            ['torrent_max_kb' => 1],
            ['torrent_max_kb' => 8193],
            ['nfo_max_kb' => 0],
            ['nfo_max_characters' => 1048577],
            ['audit_reason' => '   '],
        ] as $invalid) {
            $this->patch(route('sysop.upload-settings.update'), array_replace($this->payload(), $invalid))
                ->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('site_settings', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sysop.upload_limits.updated']);
    }

    public function test_corrupted_saved_value_is_bounded_and_cannot_raise_the_maximum(): void
    {
        app(SiteSettingsRepository::class)->set(
            UploadLimits::DEFINITIONS['torrent_max_kb']['key'],
            999999,
            'int'
        );

        $this->assertSame(8192, app(UploadLimits::class)->values()['torrent_max_kb']);
        $this->assertContains('max:8192', TorrentUploadRules::rules()['torrent_file']);

        app(SiteSettingsRepository::class)->set(
            UploadLimits::DEFINITIONS['torrent_max_kb']['key'],
            'bad-value',
            'string'
        );

        $this->assertSame(1024, app(UploadLimits::class)->values()['torrent_max_kb']);
    }

    private function payload(): array
    {
        return [
            'torrent_max_kb' => 64,
            'nfo_max_kb' => 32,
            'nfo_max_characters' => 1024,
            'audit_reason' => 'Restrict the size of torrent metadata files.',
        ];
    }

    private function torrentFileWithBytes(int $size): UploadedFile
    {
        $payload = app(BencodeService::class)->encode([
            'announce' => 'https://tracker.invalid/announce',
            'comment' => str_repeat('X', max(0, $size - 250)),
            'info' => [
                'name' => 'Test Upload',
                'length' => 4096,
                'piece length' => 16384,
                'pieces' => str_repeat('A', 20),
            ],
        ]);

        return UploadedFile::fake()->createWithContent('upload.torrent', $payload, 'application/x-bittorrent');
    }
}
