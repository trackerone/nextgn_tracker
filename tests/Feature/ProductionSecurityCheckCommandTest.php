<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\User;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    config()->set('app.env', 'production');
    config()->set('app.debug', false);
    config()->set('security.production_hardening_enabled', true);
    config()->set('security.api.require_nonce', true);
    config()->set('security.api.allow_legacy_keys', false);
    config()->set('security.api.allowed_time_skew_seconds', 120);
});

it('succeeds with secure production configuration', function (): void {
    artisan('nextgn:production-check')
        ->expectsOutput('Production security readiness check passed.')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

it('fails when app debug is enabled', function (): void {
    config()->set('app.debug', true);

    artisan('nextgn:production-check')
        ->expectsOutputToContain('Debug mode is disabled')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('fails when legacy api keys are allowed', function (): void {
    config()->set('security.api.allow_legacy_keys', true);

    artisan('nextgn:production-check')
        ->expectsOutputToContain('Legacy API keys are disabled')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('fails when nonce protection is disabled', function (): void {
    config()->set('security.api.require_nonce', false);

    artisan('nextgn:production-check')
        ->expectsOutputToContain('API nonce protection is required')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('fails when legacy plaintext keys remain', function (): void {
    ApiKey::factory()
        ->for(User::factory())
        ->legacyPlaintext('legacy-plain-key-for-readiness-test')
        ->create();

    artisan('nextgn:production-check')
        ->expectsOutputToContain('No legacy plaintext API keys remain')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

it('returns non-zero exit code on failure', function (): void {
    config()->set('security.production_hardening_enabled', false);

    artisan('nextgn:production-check')
        ->assertExitCode(SymfonyCommand::FAILURE);
});
