<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'movies' => ['Movies', 'movie'],
            'tv' => ['TV', 'tv'],
            'music' => ['Music', 'music'],
            'games' => ['Games', 'game'],
            'software' => ['Software', 'software'],
            'other' => ['Other', 'other'],
            'books' => ['Books', 'book'],
        ];

        foreach (array_keys($categories) as $index => $slug) {
            [$name, $mediaType] = $categories[$slug];
            // Seed defaults only once: never overwrite Sysop custom names,
            // ordering, media types or archived status on subsequent seeding.
            Category::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'media_type' => $mediaType,
                    'position' => $index,
                    'is_active' => true,
                ]
            );
        }
    }
}
