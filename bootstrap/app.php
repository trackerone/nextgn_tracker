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
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\RepositoryServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        $exceptions->reportable(function (AuthorizationException $exception, Request $request): void {
            $routeName = (string) ($request->route()?->getName() ?? '');

            if (! str_starts_with($routeName, 'torrents.')
                && ! str_starts_with($routeName, 'api.torrents.')
                && ! str_starts_with($routeName, 'staff.torrents.')
                && ! str_starts_with($routeName, 'api.moderation.uploads.')
            ) {
                return;
            }

            $user = $request->user();
            $action = match (true) {
                str_starts_with($routeName, 'torrents.show'),
                str_starts_with($routeName, 'api.torrents.show') => 'torrent.access.denied_details',
                str_starts_with($routeName, 'torrents.download'),
                str_starts_with($routeName, 'api.torrents.download') => 'torrent.access.denied_download',
                str_starts_with($routeName, 'staff.torrents.'),
                str_starts_with($routeName, 'api.moderation.uploads.') => 'torrent.moderation.unauthorized',
                default => 'torrent.access.denied',
            };

            $torrentRouteParam = $request->route('torrent');
            $torrentReference = $torrentRouteParam instanceof \App\Models\Torrent
                ? $torrentRouteParam->getKey()
                : (is_scalar($torrentRouteParam) ? (string) $torrentRouteParam : null);

            SecurityAuditLog::logAndWarn(
                $user instanceof User ? $user : null,
                $action,
                [
                    'route' => $routeName,
                    'torrent' => $torrentReference,
                    'message' => $exception->getMessage(),
                ]
            );
        });
    })
    ->create();
