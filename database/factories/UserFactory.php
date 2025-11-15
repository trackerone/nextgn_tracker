<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$'.Str::random(53),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_USER,
            'role_id' => Role::query()->inRandomOrder()->value('id'),
            'passkey' => bin2hex(random_bytes(16)),
        ];
    }

    public function unverified(): self
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
