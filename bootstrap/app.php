<?php

declare(strict_types=1);

use App\Http\Middleware\ActiveUserMiddleware;
use App\Http\Middleware\ApiKeyHmacMiddleware;
use App\Http\Middleware\EnsureMinimumRole;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\LockdownModeMiddleware;
use App\Http\Middleware\RequestGuard;
use App\Http\Middleware\RequireRoleLevel;
use App\Http\Middleware\ResponseGuard;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\Tracker\PassThroughAnnounceValidation;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\RepositoryServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\View\ViewServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        FilesystemServiceProvider::class,
        ViewServiceProvider::class,
        AppServiceProvider::class,
        AuthServiceProvider::class,
        EventServiceProvider::class,
        RepositoryServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role.min' => EnsureMinimumRole::class,
            'staff' => EnsureUserIsStaff::class,
            'api.hmac' => ApiKeyHmacMiddleware::class,
            'role.level' => RequireRoleLevel::class,
            'lockdown' => LockdownModeMiddleware::class,
            'tracker.validate-announce' => PassThroughAnnounceValidation::class,
        ]);

        $middleware->append([
            SecurityHeadersMiddleware::class,
            RequestGuard::class,
            ResponseGuard::class,
            LockdownModeMiddleware::class,
        ]);

        $middleware->web(append: [
            ActiveUserMiddleware::class,
        ]);

        $middleware->api(prepend: [
            sprintf('throttle:%s', config('security.api.default_rate_limit', '60,1')),
        ]);

        $middleware->api(append: [
            ActiveUserMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep the existing exception handling here without adding extra response() logic.
    })
    ->create();
