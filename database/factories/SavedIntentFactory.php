<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavedIntent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SavedIntent>
 */
final class SavedIntentFactory extends Factory
{
    protected $model = SavedIntent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'View '.Str::random(8),
            'criteria' => [
                'q' => 'matrix',
                'type' => 'movie',
            ],
        ];
    }
}
