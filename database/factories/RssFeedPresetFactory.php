<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RssFeedPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RssFeedPreset>
 */
final class RssFeedPresetFactory extends Factory
{
    protected $model = RssFeedPreset::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'public_id' => (string) Str::uuid(),
            'name' => 'Preset '.Str::random(8),
            'filters' => [
                'type' => 'movie',
                'limit' => 25,
            ],
            'is_default' => false,
        ];
    }
}
