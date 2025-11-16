<?php

declare(strict_types=1);

namespace App\Services\Logging;

use App\Models\SecurityEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SecurityEventLogger
{
    private const SEVERITY_LEVELS = ['low', 'medium', 'high', 'critical'];

    public function __construct(private readonly AuthFactory $auth) {}

    public function log(string $eventType, string $severity, string $message, array $context = []): SecurityEvent
    {
        $severity = strtolower($severity);

        if (! in_array($severity, self::SEVERITY_LEVELS, true)) {
            throw new InvalidArgumentException('Invalid security event severity.');
        }

        $user = $this->auth->guard()->user();
        $request = $this->resolveRequest();

        $event = SecurityEvent::query()->create([
            'user_id' => $user instanceof Authenticatable ? $user->getAuthIdentifier() : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'event_type' => $eventType,
            'severity' => $severity,
            'message' => $message,
            'context' => $context ?: null,
        ]);

        $this->writeFrameworkLog($severity, $message, $context + ['event_type' => $eventType]);

        return $event;
    }

    private function resolveRequest(): ?Request
    {
        if (! App::has('request')) {
            return null;
        }

        $request = App::make('request');

        return $request instanceof Request ? $request : null;
    }

    private function writeFrameworkLog(string $severity, string $message, array $context): void
    {
        $channel = Log::channel('security');
        $level = match ($severity) {
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'error',
            default => 'critical',
        };

        $channel->{$level}($message, $context);
    }
}
