<?php

declare(strict_types=1);

namespace App\Actions\Invites;

use App\Models\Invite;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IssueInviteAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(User $actor, int $maxUses, ?string $expiresAt, ?string $notes): Invite
    {
        abort_unless(PermissionService::allow($actor, 'invites.create'), 403);

        return DB::transaction(function () use ($actor, $maxUses, $expiresAt, $notes): Invite {
            $invite = Invite::query()->create([
                'code' => Str::upper(Str::random(24)),
                'inviter_user_id' => $actor->getKey(),
                'max_uses' => $maxUses,
                'expires_at' => $expiresAt,
                'notes' => $notes,
            ]);

            // Never include the code itself in an audit event; it is a signup credential.
            $this->audit->log('invites.issued', $invite, [
                'actor_user_id' => $actor->getKey(),
                'invite_id' => $invite->getKey(),
                'max_uses' => $invite->max_uses,
                'expires_at' => $invite->expires_at?->toIso8601String(),
            ]);

            return $invite;
        });
    }
}
