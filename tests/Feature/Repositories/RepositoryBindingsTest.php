<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Contracts\ConversationRepositoryInterface;
use App\Contracts\MessageRepositoryInterface;
use App\Contracts\PostRepositoryInterface;
use App\Contracts\RoleRepositoryInterface;
use App\Contracts\TopicRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Repositories\EloquentConversationRepository;
use App\Repositories\EloquentMessageRepository;
use App\Repositories\EloquentPostRepository;
use App\Repositories\EloquentRoleRepository;
use App\Repositories\EloquentTopicRepository;
use App\Repositories\EloquentUserRepository;
use Tests\TestCase;

class RepositoryBindingsTest extends TestCase
{
    public function test_repository_interfaces_resolve_to_eloquent_implementations(): void
    {
        $this->assertInstanceOf(EloquentUserRepository::class, $this->app->make(UserRepositoryInterface::class));
        $this->assertInstanceOf(EloquentTopicRepository::class, $this->app->make(TopicRepositoryInterface::class));
        $this->assertInstanceOf(EloquentPostRepository::class, $this->app->make(PostRepositoryInterface::class));
        $this->assertInstanceOf(EloquentConversationRepository::class, $this->app->make(ConversationRepositoryInterface::class));
        $this->assertInstanceOf(EloquentMessageRepository::class, $this->app->make(MessageRepositoryInterface::class));
        $this->assertInstanceOf(EloquentRoleRepository::class, $this->app->make(RoleRepositoryInterface::class));
    }
}
