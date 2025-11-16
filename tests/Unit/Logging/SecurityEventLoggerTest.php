<?php

declare(strict_types=1);

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Logging\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('security event logger persists events and writes to security channel', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $request = Request::create('/announce', 'GET');
    $request->headers->set('User-Agent', 'TestAgent/1.0');
    $request->server->set('REMOTE_ADDR', '172.16.0.1');
    app()->instance('request', $request);

    $fakeChannel = new class
    {
        public string $lastLevel = '';

        public string $lastMessage = '';

        public array $lastContext = [];

        public function info(string $message, array $context = []): void
        {
            $this->record('info', $message, $context);
        }

        public function warning(string $message, array $context = []): void
        {
            $this->record('warning', $message, $context);
        }

        public function error(string $message, array $context = []): void
        {
            $this->record('error', $message, $context);
        }

        public function critical(string $message, array $context = []): void
        {
            $this->record('critical', $message, $context);
        }

        private function record(string $level, string $message, array $context): void
        {
            $this->lastLevel = $level;
            $this->lastMessage = $message;
            $this->lastContext = $context;
        }
    };

    Log::shouldReceive('channel')->once()->with('security')->andReturn($fakeChannel);

    $logger = app(SecurityEventLogger::class);
    $event = $logger->log('tracker.invalid_passkey', 'high', 'Invalid passkey', ['foo' => 'bar']);

    expect($event)
        ->severity->toBe('high')
        ->event_type->toBe('tracker.invalid_passkey')
        ->message->toBe('Invalid passkey')
        ->context->toMatchArray(['foo' => 'bar']);

    expect($event->ip_address)->toBe('172.16.0.1');
    expect($event->user_agent)->toBe('TestAgent/1.0');
    expect(SecurityEvent::count())->toBe(1);
    expect($fakeChannel->lastLevel)->toBe('error');
    expect($fakeChannel->lastMessage)->toBe('Invalid passkey');
    expect($fakeChannel->lastContext['event_type'])->toBe('tracker.invalid_passkey');
});
