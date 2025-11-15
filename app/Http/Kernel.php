<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureUserIsActive;
<<<<<< codex/apply-zero-trust-security-hardening
use App\Http\Middleware\LockdownModeMiddleware;
=======
use App\Http\Middleware\RedirectIfAuthenticated;
>>>>>> main
use App\Http\Middleware\RequestGuard;
use App\Http\Middleware\ResponseGuard;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\Tracker\ValidateAnnounceRequest;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\AuthenticateSession;

class Kernel extends HttpKernel
{
    /**
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        HandleCors::class,
        ValidatePostSize::class,
        PreventRequestsDuringMaintenance::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        SecurityHeadersMiddleware::class,
        RequestGuard::class,
        ResponseGuard::class,
        EnsureUserIsActive::class,
        LockdownModeMiddleware::class,
    ];

    /**
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'precognitive' => HandlePrecognitiveRequests::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'tracker.validate-announce' => ValidateAnnounceRequest::class,
        'lockdown' => LockdownModeMiddleware::class,
    ];

}
