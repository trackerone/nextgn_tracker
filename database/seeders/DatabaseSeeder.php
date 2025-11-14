<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
        ]);

        if (app()->environment(['local', 'development', 'testing'])) {
            $this->call(DemoContentSeeder::class);
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role_id')) {
            return;
        }

        $defaultRoleId = Role::query()
            ->where('slug', Role::DEFAULT_SLUG)
            ->value('id');

        if ($defaultRoleId === null) {
            return;
        }

        User::query()
            ->whereNull('role_id')
            ->update(['role_id' => $defaultRoleId]);
    }
}
