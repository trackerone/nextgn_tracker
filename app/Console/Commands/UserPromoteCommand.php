<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class UserPromoteCommand extends Command
{
    protected $signature = 'user:promote {email : Email address of the user} {roleSlug : Role slug to promote the user to}';

    protected $description = 'Promote a user to the given role slug.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $roleSlug = (string) $this->argument('roleSlug');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User with email '{$email}' was not found.");

            return self::FAILURE;
        }

        $role = Role::query()->where('slug', $roleSlug)->first();

        if ($role === null) {
            $this->error("Role with slug '{$roleSlug}' was not found.");

            return self::FAILURE;
        }

        $user->forceFill(['role_id' => $role->getKey()])->save();

        $this->info("User '{$user->email}' promoted to role '{$role->name}' ({$role->slug}).");

        return self::SUCCESS;
    }
}
