<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\DownloadEligibilityService;
use App\Services\Torrents\UploadEligibilityService;
use Illuminate\Auth\Access\Response;

class TorrentPolicy
{
    public function __construct(
        private readonly DownloadEligibilityService $downloadEligibility,
        private readonly UploadEligibilityService $uploadEligibility,
    ) {}

    public function view(User $user, Torrent $torrent): Response
    {
        if ($torrent->isApproved()) {
            return Response::allow();
        }

        if ($this->canAccessModeration($user) || $torrent->user_id === $user->id) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function viewModerationListings(User $user): bool
    {
        return $this->canAccessModeration($user);
    }

    public function viewModerationItem(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    public function publish(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    public function reject(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    public function download(User $user, Torrent $torrent): Response
    {
        $decision = $this->downloadEligibility->decide($user, $torrent);

        if ($decision->allowed) {
            return Response::allow();
        }

        // Domain telemetry for why eligibility rejected this download attempt.
        SecurityAuditLog::logAndWarn($user, 'torrent.download.eligibility', [
            'torrent_id' => (int) $torrent->getKey(),
            'allowed' => false,
            'reason' => $decision->reason,
            'telemetry_scope' => 'eligibility_decision',
        ]);

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $this->uploadEligibility->decide($user)->allowed;
    }

    public function update(User $user, Torrent $torrent): bool
    {
        if ($this->canAccessModeration($user)) {
            return true;
        }

        return $torrent->user_id === $user->id && ! $torrent->isSoftDeleted();
    }

    public function delete(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    public function moderate(User $user, Torrent $torrent): bool
    {
        return $this->canAccessModeration($user);
    }

    private function canAccessModeration(User $user): bool
    {
        return $user->isStaff();
    }
}
