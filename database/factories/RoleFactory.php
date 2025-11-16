<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $mapping = [
            'sysop' => 12,
            'admin2' => 11,
            'admin1' => 10,
            'mod2' => 9,
            'mod1' => 8,
            'uploader3' => 7,
            'uploader2' => 6,
            'uploader1' => 5,
            'user4' => 4,
            'user3' => 3,
            'user2' => 2,
            'user1' => 1,
            'newbie' => 0,
        ];

        $slug = $this->faker->unique()->randomElement(array_keys($mapping));

        return [
            'slug' => $slug,
            'name' => ucfirst($slug),
            'level' => $mapping[$slug],
            'is_staff' => $mapping[$slug] >= Role::STAFF_LEVEL_THRESHOLD,
        ];
    }

    public function withSlug(string $slug): self
    {
        return $this->state(fn () => [
            'slug' => $slug,
            'name' => ucfirst($slug),
        ]);
    }
}
