<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Policies\ConversationPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PostPolicy;
use App\Policies\TopicPolicy;
use App\Support\Roles\RoleLevel;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Conversation::class => ConversationPolicy::class,
        Message::class => MessagePolicy::class,
        Topic::class => TopicPolicy::class,
        Post::class => PostPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(static function (?User $user): ?bool {
            if ($user === null) {
                return null;
            }

            return RoleLevel::atLeast($user, RoleLevel::SYSOP_LEVEL) ? true : null;
        });

        Gate::define('isAdmin', static function (User $user): bool {
            return RoleLevel::atLeast($user, RoleLevel::ADMIN_LEVEL);
        });

        Gate::define('isModerator', static function (User $user): bool {
            return RoleLevel::atLeast($user, RoleLevel::MODERATOR_LEVEL);
        });

        Gate::define('isUploader', static function (User $user): bool {
            return RoleLevel::atLeast($user, RoleLevel::UPLOADER_LEVEL);
        });

        Gate::define('isUser', static function (User $user): bool {
            return RoleLevel::atLeast($user, RoleLevel::USER_LEVEL);
        });
    }
}
