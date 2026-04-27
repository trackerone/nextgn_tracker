<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Torrent;
use App\Models\TorrentExternalMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TorrentExternalMetadata>
 */
final class TorrentExternalMetadataFactory extends Factory
{
    protected $model = TorrentExternalMetadata::class;

    public function definition(): array
    {
        return [
            'torrent_id' => Torrent::factory(),
            'imdb_id' => 'tt'.(string) fake()->numberBetween(1000000, 9999999),
            'tmdb_id' => (string) fake()->numberBetween(10000, 99999),
            'trakt_id' => (string) fake()->numberBetween(10000, 99999),
            'trakt_slug' => fake()->slug(),
            'title' => fake()->sentence(3),
            'original_title' => fake()->sentence(3),
            'year' => fake()->numberBetween(1980, 2026),
            'media_type' => fake()->randomElement(['movie', 'tv']),
            'overview' => fake()->paragraph(),
            'poster_path' => '/poster.jpg',
            'poster_url' => 'https://img.example/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'backdrop_url' => 'https://img.example/backdrop.jpg',
            'tmdb_url' => 'https://www.themoviedb.org/movie/1',
            'imdb_url' => 'https://www.imdb.com/title/tt1234567/',
            'trakt_url' => 'https://trakt.tv/movies/example',
            'providers_payload' => ['tmdb' => ['id' => 1]],
            'enriched_at' => now(),
            'enrichment_status' => 'enriched',
            'last_error' => null,
        ];
    }
}
