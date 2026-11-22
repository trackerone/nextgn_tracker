<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Operations\OperationsHealthService;
use App\Services\Operations\OperationsProbe;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class SysopPassiveDiagnosticsTest extends TestCase
{
    public function test_passive_probe_and_health_collection_never_write_test_cache_keys_or_storage_files(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('get')->andReturn(null);
        $store->shouldNotReceive('put');
        $store->shouldNotReceive('forget');
        Cache::shouldReceive('store')->andReturn($store);

        $before = glob(storage_path('app/ops-*')) ?: [];
        $probe = app(OperationsProbe::class)->collect();
        $health = app(OperationsHealthService::class)->collect();
        $after = glob(storage_path('app/ops-*')) ?: [];

        $checks = collect($probe['checks'])->keyBy('id');
        $cards = collect($health['cards'])->keyBy('group');

        self::assertSame('unknown', $checks['cache']['status']);
        self::assertSame('unknown', $checks['storage']['status']);
        self::assertSame('info', $cards['Cache/Redis']['status']);
        self::assertSame($before, $after);
    }

    public function test_sysop_sees_passive_diagnostics_and_support_bundle_without_running_active_checks(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->actingAs($sysop)->get(route('sysop.operations.index'))
            ->assertOk()
            ->assertSee('Passive status')
            ->assertSee('Run active cache and storage checks');

        $this->actingAs($sysop)->get(route('sysop.operations.support-bundle'))
            ->assertOk()
            ->assertJsonPath('schema_version', 1)
            ->assertJsonPath('checks.1.id', 'cache')
            ->assertJsonPath('checks.1.status', 'unknown');
    }

    public function test_only_sysop_may_explicitly_run_active_write_checks(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($member)->post(route('sysop.operations.diagnostics.run'))->assertForbidden();

        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $response = $this->actingAs($sysop)->post(route('sysop.operations.diagnostics.run'));

        $response->assertRedirect(route('sysop.operations.index'));
        $response->assertSessionHas('operations_active_diagnostics');
        $results = session('operations_active_diagnostics');
        self::assertIsArray($results);
        self::assertSame(['cache', 'storage'], array_column($results['checks'], 'id'));

        $this->get(route('sysop.operations.index'))
            ->assertOk()
            ->assertSee('Active diagnostics completed at');
    }
}
