<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('audit logger records action metadata and request context', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    actingAs($actor);

    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'PHPUnit');
    $request->server->set('REMOTE_ADDR', '10.0.0.1');
    app()->instance('request', $request);

    $logger = app(AuditLogger::class);
    $log = $logger->log('user.role_changed', $target, ['foo' => 'bar']);

    expect($log)
        ->action->toBe('user.role_changed')
        ->user_id->toBe($actor->getKey())
        ->target_type->toBe($target->getMorphClass())
        ->target_id->toBe($target->getKey())
        ->metadata->toMatchArray(['foo' => 'bar']);

    expect($log->ip_address)->toBe('10.0.0.1');
    expect($log->user_agent)->toBe('PHPUnit');
});

test('audit logger works without authenticated user', function (): void {
    $request = Request::create('/test', 'POST');
    $request->headers->set('User-Agent', 'CLI');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    app()->instance('request', $request);

    $logger = app(AuditLogger::class);
    $log = $logger->log('system.task', null, []);

    expect($log->user_id)->toBeNull();
    expect($log->action)->toBe('system.task');
    expect($log->metadata)->toBeNull();
    expect(AuditLog::count())->toBe(1);
});
