<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        // Vi undgår Faker "name" fuldstændigt for at slippe for Unknown format "name"
        $unique = Str::uuid()->toString();

        return [
            'name' => 'Test User '.$unique,
            'email' => 'user_'.$unique.'@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_USER,
            'role_id' => Role::query()->where('slug', 'newbie')->value('id'),
            'is_banned' => false,
            'is_disabled' => false,
            'passkey' => substr(hash('sha256', $unique), 0, 32),
            'is_staff' => false,
        ];
    }

    public function staff(): self
    {
        return $this->state(function (): array {
            $role = Role::query()->whereIn('slug', ['mod1', 'admin1', 'sysop'])->first();

            return [
                'role' => $role?->slug ?? 'mod1',
                'role_id' => $role?->id,
                'is_staff' => true,
            ];
        });
    }
}
