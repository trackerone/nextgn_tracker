<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates audit logs for moderation actions', function (): void {
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $pendingForApproval = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING]);
    $pendingForRejection = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING]);
    $pendingForSoftDelete = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING]);

    actingAs($moderator);

    post(route('staff.torrents.approve', $pendingForApproval))->assertRedirect();
    post(route('staff.torrents.reject', $pendingForRejection), ['reason' => 'bad metadata'])->assertRedirect();
    post(route('staff.torrents.soft_delete', $pendingForSoftDelete))->assertRedirect();

    expect(AuditLog::where('action', 'torrent.approved')->count())->toBe(1);
    expect(AuditLog::where('action', 'torrent.rejected')->count())->toBe(1);
    expect(AuditLog::where('action', 'torrent.soft_deleted')->count())->toBe(1);

    $rejectLog = AuditLog::where('action', 'torrent.rejected')->first();
    expect($rejectLog->metadata['reason'] ?? null)->toBe('bad metadata');
});
