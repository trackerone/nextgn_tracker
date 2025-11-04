<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'sysop', 'name' => 'Sysop', 'level' => 12],
            ['slug' => 'admin2', 'name' => 'Admin 2', 'level' => 11],
            ['slug' => 'admin1', 'name' => 'Admin 1', 'level' => 10],
            ['slug' => 'mod2', 'name' => 'Moderator 2', 'level' => 9],
            ['slug' => 'mod1', 'name' => 'Moderator 1', 'level' => 8],
            ['slug' => 'uploader3', 'name' => 'Uploader 3', 'level' => 7],
            ['slug' => 'uploader2', 'name' => 'Uploader 2', 'level' => 6],
            ['slug' => 'uploader1', 'name' => 'Uploader 1', 'level' => 5],
            ['slug' => 'user4', 'name' => 'User 4', 'level' => 4],
            ['slug' => 'user3', 'name' => 'User 3', 'level' => 3],
            ['slug' => 'user2', 'name' => 'User 2', 'level' => 2],
            ['slug' => 'user1', 'name' => 'User 1', 'level' => 1],
            ['slug' => 'newbie', 'name' => 'Newbie', 'level' => 0],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'level' => $role['level'],
                    'is_staff' => $role['level'] >= Role::STAFF_LEVEL_THRESHOLD,
                ]
            );
        }
    }
}
