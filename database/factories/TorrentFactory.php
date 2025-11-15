<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Torrent>
 */
class TorrentFactory extends Factory
{
    protected $model = Torrent::class;

    public function definition(): array
    {
        $name = $this->faker->sentence(3);

        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->uuid()),
            'info_hash' => Str::upper(bin2hex(random_bytes(20))),
            'storage_path' => sprintf('torrents/%s/%s/%s.torrent', now()->format('Y'), now()->format('m'), Str::uuid()),
            'size_bytes' => $this->faker->numberBetween(1_000_000, 50_000_000_000),
            'file_count' => $this->faker->numberBetween(1, 400),
            'type' => $this->faker->randomElement(['movie', 'tv', 'music', 'game', 'software', 'other']),
            'source' => $this->faker->randomElement(['web', 'bluray', 'hdtv', null]),
            'resolution' => $this->faker->randomElement(['2160p', '1080p', '720p', null]),
            'codecs' => [
                'video' => $this->faker->randomElement(['x264', 'x265', 'AV1']),
                'audio' => $this->faker->randomElement(['AAC', 'DTS', 'FLAC']),
            ],
            'tags' => $this->faker->randomElements([
                'scene',
                'remux',
                'proper',
                'internal',
            ], 2),
            'seeders' => $this->faker->numberBetween(0, 5_000),
            'leechers' => $this->faker->numberBetween(0, 5_000),
            'completed' => $this->faker->numberBetween(0, 10_000),
            'is_visible' => true,
            'is_approved' => true,
            'is_banned' => false,
            'ban_reason' => null,
            'freeleech' => false,
            'status' => Torrent::STATUS_APPROVED,
            'moderated_by' => null,
            'moderated_at' => null,
            'moderated_reason' => null,
            'description' => $this->faker->paragraph(),
            'nfo_text' => $this->faker->optional()->text(2000),
            'nfo_storage_path' => $this->faker->optional()->passthrough(
                sprintf('nfo/%s/%s/%s.nfo', now()->format('Y'), now()->format('m'), Str::uuid())
            ),
            'imdb_id' => $this->faker->optional()->lexify('tt???????'),
            'tmdb_id' => $this->faker->optional()->numerify('######'),
            'original_filename' => $this->faker->slug.'.torrent',
            'uploaded_at' => now(),
        ];
    }
}
