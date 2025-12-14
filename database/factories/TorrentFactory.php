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
        $year = now()->format('Y');
        $month = now()->format('m');

        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->uuid()),
            'info_hash' => Str::upper(bin2hex(random_bytes(20))),
            'storage_path' => sprintf('torrents/%s/%s/%s.torrent', $year, $month, (string) Str::uuid()),
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

            // Defaults for "normal" tracker flows
            'seeders' => 0,
            'leechers' => 0,
            'completed' => 0,

            // Default = approved + visible (så alle “happy path” tests virker uden ekstra state)
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
                sprintf('nfo/%s/%s/%s.nfo', $year, $month, (string) Str::uuid())
            ),
            'imdb_id' => $this->faker->optional()->lexify('tt???????'),
            'tmdb_id' => $this->faker->optional()->numerify('######'),
            'original_filename' => $this->faker->slug.'.torrent',
            'uploaded_at' => now(),
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'is_approved' => true,
            'status' => Torrent::STATUS_APPROVED,
        ]);
    }

    public function unapproved(): self
    {
        return $this->state(fn (): array => [
            'is_approved' => false,
            'status' => Torrent::STATUS_PENDING,
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'is_approved' => false,
            'status' => Torrent::STATUS_REJECTED,
        ]);
    }

    public function softDeleted(): self
    {
        return $this->state(fn (): array => [
            'status' => Torrent::STATUS_SOFT_DELETED,
        ]);
    }

    public function banned(?string $reason = 'Banned by staff'): self
    {
        return $this->state(fn (): array => [
            'is_banned' => true,
            'ban_reason' => $reason,
        ]);
    }
}
