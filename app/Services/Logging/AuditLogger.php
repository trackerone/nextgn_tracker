<?php

declare(strict_types=1);

namespace App\Services\Logging;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AuditLogger
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function log(string $action, ?Model $target = null, array $metadata = []): AuditLog
    {
        $user = $this->auth->guard()->user();
        $request = $this->resolveRequest();

        return AuditLog::query()->create([
            'user_id' => $user instanceof Authenticatable ? $user->getAuthIdentifier() : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'metadata' => $metadata ?: null,
        ]);
    }

    private function resolveRequest(): ?Request
    {
        if (! App::has('request')) {
            return null;
        }

        return App::make(Request::class);
    }
}
