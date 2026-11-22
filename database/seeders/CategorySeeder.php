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

        foreach ($categories as $slug => [$name, $mediaType]) {
            // Seed defaults only once: never overwrite Sysop custom names,
            // ordering, media types or archived status on subsequent seeding.
            Category::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'media_type' => $mediaType,
                    'position' => array_search($slug, array_keys($categories), true),
                    'is_active' => true,
                ]
            );
        }
    }
}
