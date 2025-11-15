<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\Security\AuthEventSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, class-string>
     */
    protected $subscribe = [
        AuthEventSubscriber::class,
    ];
}
