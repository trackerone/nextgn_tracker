<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ProductionReadinessTest extends TestCase
{
    public function test_env_example_defaults_are_safe_for_production(): void
    {
        $envExample = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_ENV=production', $envExample);
        $this->assertStringContainsString('APP_DEBUG=false', $envExample);
        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $envExample);
        $this->assertStringContainsString('DB_CONNECTION=', $envExample);
    }

    public function test_docker_runtime_uses_non_root_user_and_prepares_writable_paths(): void
    {
        $dockerfile = (string) file_get_contents(base_path('Dockerfile'));
        $entrypoint = (string) file_get_contents(base_path('tools/entrypoint.sh'));

        $this->assertStringContainsString('USER nextgn', $dockerfile);
        $this->assertStringContainsString('chown -R nextgn:nextgn /app', $dockerfile);
        $this->assertStringNotContainsString('chmod -R 0777', $dockerfile);
        $this->assertStringContainsString('ensure_dir storage/logs', $entrypoint);
        $this->assertStringContainsString('ensure_dir bootstrap/cache', $entrypoint);
        $this->assertStringContainsString('php artisan storage:link', $entrypoint);
    }

    public function test_production_entrypoint_requires_safe_app_configuration(): void
    {
        $entrypoint = (string) file_get_contents(base_path('tools/entrypoint.sh'));

        $this->assertStringContainsString('require_env APP_KEY', $entrypoint);
        $this->assertStringContainsString('require_env DB_CONNECTION', $entrypoint);
        $this->assertStringContainsString('Refusing to start production with APP_DEBUG enabled.', $entrypoint);
        $this->assertStringContainsString('php artisan config:cache', $entrypoint);
        $this->assertStringContainsString('php artisan route:cache', $entrypoint);
    }
}
