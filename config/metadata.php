<?php

declare(strict_types=1);

return [
    'tmdb' => [
        'api_key' => env('TMDB_API_KEY'),
        'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
        'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/original'),
    ],
    'trakt' => [
        'client_id' => env('TRAKT_CLIENT_ID'),
        'base_url' => env('TRAKT_BASE_URL', 'https://api.trakt.tv'),
    ],
    'imdb' => [
        'dataset_enabled' => env('IMDB_DATASET_ENABLED', false),
    ],
];
