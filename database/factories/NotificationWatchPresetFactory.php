<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotificationWatchPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationWatchPreset>
 */
final class NotificationWatchPresetFactory extends Factory
{
    protected $model = NotificationWatchPreset::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Watch '.Str::random(8),
            'filters' => [
                'type' => 'movie',
            ],
            'is_enabled' => true,
            'last_checked_at' => null,
        ];
    }
}
