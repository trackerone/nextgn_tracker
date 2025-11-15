<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\Roles\RoleLevel;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(static function (?User $user): ?bool {
            if ($user === null) {
                return false;
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
