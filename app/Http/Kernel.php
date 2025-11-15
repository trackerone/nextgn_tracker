<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequestGuard;
use App\Http\Middleware\ResponseGuard;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;

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
        RequestGuard::class,
        ResponseGuard::class,
        EnsureUserIsActive::class,
    ];

}
