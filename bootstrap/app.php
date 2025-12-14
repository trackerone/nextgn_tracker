<?php

declare(strict_types=1);

use App\Http\Middleware\ActiveUserMiddleware;
use App\Http\Middleware\EnsureMinimumRole;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\ValidateAnnounceRequest;
use App\Providers\AuthServiceProvider;
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
        AuthServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role.min' => EnsureMinimumRole::class,
            'staff' => EnsureUserIsStaff::class,

            // Tracker
            'tracker.validate-announce' => ValidateAnnounceRequest::class,
        ]);

        // IMPORTANT: actually run the middleware for all web routes (incl. torrents.show)
        $middleware->web(append: [
            ActiveUserMiddleware::class,
        ]);

        // Optional but recommended if you want the same behavior for API routes:
        $middleware->api(append: [
            ActiveUserMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep the existing exception handling here without adding extra response() logic.
    })
    ->create();
