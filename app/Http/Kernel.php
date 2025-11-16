<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\ApiKeyHmacMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LockdownModeMiddleware;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RequestGuard;
use App\Http\Middleware\ResponseGuard;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\Tracker\ValidateAnnounceRequest;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],
        'api' => [
            SubstituteBindings::class,
        ],
    ];

    public function __construct(Application $app, Router $router)
    {
        $rateLimit = sprintf('throttle:%s', config('security.api.default_rate_limit', '60,1'));
        $this->middlewareGroups['api'] = array_merge([$rateLimit], $this->middlewareGroups['api']);

        parent::__construct($app, $router);
    }

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
        'api.hmac' => ApiKeyHmacMiddleware::class,
    ];
}
