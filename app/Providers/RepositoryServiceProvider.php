<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ConversationRepositoryInterface;
use App\Contracts\MessageRepositoryInterface;
use App\Contracts\PostRepositoryInterface;
use App\Contracts\RoleRepositoryInterface;
use App\Contracts\TopicRepositoryInterface;
use App\Contracts\TorrentRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Repositories\EloquentConversationRepository;
use App\Repositories\EloquentMessageRepository;
use App\Repositories\EloquentPostRepository;
use App\Repositories\EloquentRoleRepository;
use App\Repositories\EloquentTopicRepository;
use App\Repositories\EloquentTorrentRepository;
use App\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->scoped(TopicRepositoryInterface::class, EloquentTopicRepository::class);
        $this->app->scoped(PostRepositoryInterface::class, EloquentPostRepository::class);
        $this->app->scoped(ConversationRepositoryInterface::class, EloquentConversationRepository::class);
        $this->app->scoped(MessageRepositoryInterface::class, EloquentMessageRepository::class);
        $this->app->scoped(RoleRepositoryInterface::class, EloquentRoleRepository::class);
        $this->app->scoped(TorrentRepositoryInterface::class, EloquentTorrentRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
