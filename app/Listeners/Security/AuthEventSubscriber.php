<?php

declare(strict_types=1);

namespace App\Listeners\Security;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;

class AuthEventSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [$this, 'handleLogin']);
        $events->listen(Failed::class, [$this, 'handleLoginFailed']);
        $events->listen(Logout::class, [$this, 'handleLogout']);
        $events->listen(PasswordReset::class, [$this, 'handlePasswordReset']);
    }

    public function handleLogin(Login $event): void
    {
        $user = $this->resolveUser($event->user);

        SecurityAuditLog::log($user, 'auth.login.success', [
            'ip' => request()->ip(),
        ]);
    }

    public function handleLoginFailed(Failed $event): void
    {
        SecurityAuditLog::logAndWarn(null, 'auth.login.failed', [
            'email' => $event->credentials['email'] ?? null,
            'ip' => request()->ip(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $user = $this->resolveUser($event->user);

        SecurityAuditLog::log($user, 'auth.logout', [
            'ip' => request()->ip(),
        ]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $this->resolveUser($event->user);

        SecurityAuditLog::log($user, 'auth.password.reset.completed', [
            'ip' => request()->ip(),
        ]);
    }

    private function resolveUser(Authenticatable|User|null $user): ?User
    {
        return $user instanceof User ? $user : null;
    }
}
