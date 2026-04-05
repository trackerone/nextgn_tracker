<?php

declare(strict_types=1);

namespace App\Actions\Torrents;

use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class ResolveTorrentAccessAction
{
    /**
     * @param  array<int, string>  $with
     *
     * @throws AuthorizationException
     */
    public function execute(string|int $identifier, string $ability, array $with = []): Torrent
    {
        $query = Torrent::query();

        if ($with !== []) {
            $query->with($with);
        }

        $torrent = $query
            ->where(static function ($builder) use ($identifier): void {
                $builder
                    ->where('id', (string) $identifier)
                    ->orWhere('slug', (string) $identifier);
            })
            ->firstOrFail();

<<< fix/post-merge-ci-recovery-slice2
        try {
            Gate::authorize($ability, $torrent);
        } catch (AuthorizationException $exception) {
            $this->logDeniedAccess($ability, $torrent);

            throw $exception;
        }
=======
        $response = Gate::inspect($ability, $torrent);

        if ($response->denied()) {
            $action = match ($ability) {
                'download' => 'torrent.access.denied_download',
                'view' => 'torrent.access.denied_details',
                default => null,
            };

            if ($action !== null) {
                SecurityAuditLog::logAndWarn(
                    auth()->user(),
                    $action,
                    [
                        'ability' => $ability,
                        'torrent' => $torrent->getKey(),
                    ]
                );
            }
        }

        $response->authorize();
>>> main

        return $torrent;
    }

    private function logDeniedAccess(string $ability, Torrent $torrent): void
    {
        $action = match ($ability) {
            'view' => 'torrent.access.denied_details',
            'download' => 'torrent.access.denied_download',
            default => null,
        };

        if ($action === null) {
            return;
        }

        $user = request()->user();

        SecurityAuditLog::logAndWarn(
            $user instanceof User ? $user : null,
            $action,
            [
                'torrent_id' => $torrent->id,
                'route' => (string) (request()->route()?->getName() ?? ''),
                'ability' => $ability,
            ]
        );
    }
}
