<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RssFeedRequest;
use App\Models\User;
use App\Services\Rss\TorrentRssFeedBuilder;
use Illuminate\Http\Response;

final class RssFeedController extends Controller
{
    public function __invoke(string $token, RssFeedRequest $request, TorrentRssFeedBuilder $builder): Response
    {
        $user = User::query()
            ->where('rss_token', $token)
            ->firstOrFail();

        return response($builder->build($user, $request->filters()), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
