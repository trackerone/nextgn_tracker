<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RssFeedPreset;
use App\Models\User;
use App\Services\Rss\RssFeedFilterNormalizer;
use App\Services\Rss\TorrentRssFeedBuilder;
use Illuminate\Http\Response;

final class RssPresetFeedController extends Controller
{
    public function __invoke(
        string $token,
        string $preset,
        RssFeedFilterNormalizer $normalizer,
        TorrentRssFeedBuilder $builder,
    ): Response {
        $user = User::query()
            ->where('rss_token', $token)
            ->firstOrFail();

        $feedPreset = RssFeedPreset::query()
            ->where('user_id', $user->id)
            ->where('public_id', $preset)
            ->firstOrFail();

        return response($builder->build($user, $normalizer->normalize($feedPreset->filters)), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
