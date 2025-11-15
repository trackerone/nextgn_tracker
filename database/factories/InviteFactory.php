<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invite>
 */
class InviteFactory extends Factory
{
    protected $model = Invite::class;

    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(16)),
            'inviter_user_id' => User::factory(),
            'max_uses' => 1,
            'uses' => 0,
            'expires_at' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
