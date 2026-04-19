<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Torrents\TorrentFollowNavigationBadge;
use App\Services\Torrents\UploadPreflightContextBuilder;
use App\Services\Torrents\UploadPreflightContextBuilderContract;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UploadPreflightContextBuilderContract::class,
            UploadPreflightContextBuilder::class
        );
    }

    public function boot(): void
    {
        RateLimiter::for('torrent-download', static function (Request $request): Limit {
            [$maxAttempts, $decayMinutes] = array_pad(
                array_map('intval', explode(',', (string) config('security.rate_limits.torrent_download', '45,1'), 2)),
                2,
                1
            );

            return Limit::perMinutes(max($decayMinutes, 1), max($maxAttempts, 1))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        if (app()->environment('production')) {
            config(['app.debug' => false]);
        }

        config([
            'session.secure' => config('session.secure', app()->environment('production')),
            'session.http_only' => config('session.http_only', true),
            'session.same_site' => config('session.same_site', 'strict'),
        ]);

        $this->registerTorrentRateLimiters();

        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();

            if (! $user instanceof User) {
                $view->with('followNavNewCount', 0);

                return;
            }

            $badge = app(TorrentFollowNavigationBadge::class);

            $view->with(
                'followNavNewCount',
                $badge->unseenCountFor($user)
            );
        });
    }

    private function registerTorrentRateLimiters(): void
    {
        RateLimiter::for('torrent-browse', function (Request $request): Limit {
            return $this->limitFromConfig(
                (string) config('security.rate_limits.torrent_browse', '60,1'),
                $request,
                'torrent-browse'
            );
        });

        RateLimiter::for('torrent-details', function (Request $request): Limit {
            return $this->limitFromConfig(
                (string) config('security.rate_limits.torrent_details', '90,1'),
                $request,
                'torrent-details'
            );
        });

        RateLimiter::for('torrent-download', function (Request $request): Limit {
            return $this->limitFromConfig(
                (string) config('security.rate_limits.torrent_download', '45,1'),
                $request,
                'torrent-download'
            );
        });

        RateLimiter::for('torrent-moderation', function (Request $request): Limit {
            return $this->limitFromConfig(
                (string) config(
                    'security.rate_limits.torrent_moderation',
                    config('security.rate_limits.moderation', '60,1')
                ),
                $request,
                'torrent-moderation'
            );
        });
    }

    private function limitFromConfig(string $value, Request $request, string $prefix): Limit
    {
        [$maxAttempts, $decayMinutes] = array_pad(
            array_map('intval', explode(',', $value, 2)),
            2,
            1
        );

        $maxAttempts = max(1, $maxAttempts);
        $decayMinutes = max(1, $decayMinutes);

        $key = $request->user()?->id
            ? sprintf('%s:user:%s', $prefix, (string) $request->user()->id)
            : sprintf('%s:ip:%s', $prefix, (string) $request->ip());

        return Limit::perMinutes($decayMinutes, $maxAttempts)->by($key);
    }
}
