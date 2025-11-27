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
            // IMPORTANT:
            // With the Laravel 11 "hashed" cast, assigning a plain string here
            // causes Laravel to hash it correctly.
            'password' => 'password',
            'remember_token' => Str::random(10),
            // IMPORTANT:
            // Passkeys are NOT hashed values. Ensure this is null unless created explicitly.
            'passkey' => null,
            // If your User model has a role_id column, set it to null.
            // If it does not exist, skip it.
            'role_id' => null,
        ];
    }

    public function unverified(): self
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }

    private function defaultRoleId(): int
    {
        $preferred = Role::query()->where('slug', 'user1')->value('id')
            ?? Role::query()->where('slug', Role::DEFAULT_SLUG)->value('id');

        return $preferred ?? Role::factory()->withSlug(Role::DEFAULT_SLUG)->create()->getKey();
    }
}
