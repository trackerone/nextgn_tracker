<?php

declare(strict_types=1);

namespace App\Services\Hnr;

use App\Models\HnrAccount;
use App\Models\HnrObligation;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Settings\TrackerRuntimeSettings;
use App\Services\Tracker\AnnounceIntegrityEvaluation;
use App\Tracker\Announce\AnnounceRequestData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class HnrService
{
    public function __construct(private readonly HnrSettings $settings, private readonly TrackerRuntimeSettings $tracker) {}

    public function record(User $user, Torrent $torrent, AnnounceRequestData $data, AnnounceIntegrityEvaluation $credit, ?Peer $oldPeer): void
    {
        if (! $this->settings->enabled() || $this->settings->exempt($user) || (int) $torrent->user_id === (int) $user->id) {
            return;
        }

        DB::transaction(function () use ($user, $torrent, $data, $credit, $oldPeer): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $row = HnrObligation::query()->where('user_id', $user->id)->where('torrent_id', $torrent->id)->lockForUpdate()->first();
            if ($row === null && $credit->downloadedDelta <= 0) {
                return;
            }
            $rules = $this->settings->values();
            $row ??= HnrObligation::query()->create([
                'user_id' => $user->id,
                'torrent_id' => $torrent->id,
                'required_seed_seconds' => $rules['seed_hours'] * 3600,
                'required_ratio' => $rules['ratio'],
                'last_seen_at' => now(),
                'last_counted_at' => now(),
            ]);
            // Physical validated bytes: freeleech ratio credit must not erase seed obligations.
            $row->downloaded_bytes += $credit->downloadedDelta;
            $row->uploaded_bytes += $credit->uploadedDelta;
            if ($oldPeer !== null && $oldPeer->last_announce_at !== null
                && $oldPeer->last_announce_at->gt(now()->subMinutes($this->tracker->ghostTimeout()))
                && (int) $oldPeer->left === 0 && $data->left === 0 && ! $credit->isSuspicious()) {
                $from = $oldPeer->last_announce_at->max($row->last_counted_at);
                $row->seed_seconds += max(0, (int) $from->diffInSeconds(now(), false));
            }
            if ($data->left === 0 && ! $credit->isSuspicious()) {
                $row->completed_at ??= now();
            }
            if ($data->event !== 'stopped' || $oldPeer !== null) {
                $row->last_seen_at = now();
            }
            $row->last_counted_at = now();
            $met = $row->downloaded_bytes > 0 && $row->uploaded_bytes >= $row->downloaded_bytes * $row->required_ratio;
            $met = $met || ($row->completed_at !== null && $row->seed_seconds >= $row->required_seed_seconds);
            $row->fulfilled_at = $met ? ($row->fulfilled_at ?? now()) : null;
            $row->save();
        });
    }

    public function outstanding(User $user): Builder
    {
        return HnrObligation::query()->where('user_id', $user->id)->whereNull('fulfilled_at')->whereNull('waived_at')
            ->whereIn('torrent_id', Torrent::query()->displayable()->select('id'));
    }

    public function overdueCount(User $user): int
    {
        $rules = $this->settings->values();

        return $this->outstanding($user)->where('last_seen_at', '<=', now()->subHours($rules['grace_hours']))
            ->whereNotIn('torrent_id', Peer::query()->select('torrent_id')->where('user_id', $user->id)
                ->where('last_announce_at', '>', now()->subMinutes($this->tracker->ghostTimeout())))
            ->count();
    }

    public function restricted(User $user): bool
    {
        return $this->settings->enabled() && ! $this->settings->exempt($user)
            && HnrAccount::query()->where('user_id', $user->id)->whereNotNull('restricted_at')->exists();
    }

    public function recovering(User $user, Torrent $torrent): bool
    {
        return (int) $torrent->user_id === (int) $user->id
            || HnrObligation::query()->where('user_id', $user->id)->where('torrent_id', $torrent->id)->exists();
    }

    public function evaluate(User $user): void
    {
        if (! $this->settings->enabled()) {
            return;
        }
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $state = HnrAccount::query()->firstOrCreate(['user_id' => $user->id]);
            $rules = $this->settings->values();
            while ($state->warnings > 0 && $state->decay_at?->lte(now())) {
                $state->warnings--;
                $state->decay_at = $state->decay_at->addDays($rules['warning_decay_days']);
                $this->event($user, 'warning_expired', 'One HnR warning expired after a clean interval.');
            }
            $overdue = $this->settings->exempt($user) ? 0 : $this->overdueCount($user);
            if ($overdue < $rules['torrent_threshold']) {
                if ($state->restricted_at !== null) {
                    $state->restricted_at = null;
                    $this->event($user, 'restored', 'Download access restored: overdue obligations are below the configured threshold.');
                }
            } elseif ($state->restricted_at === null && ($state->next_warning_at === null || $state->next_warning_at->lte(now()))) {
                if ($state->warnings >= $rules['warnings_before_restriction']) {
                    $state->restricted_at = now();
                    $this->event($user, 'restricted', 'New downloads are restricted. Resume outstanding torrents or ask staff to review your obligations.');
                } else {
                    $state->warnings++;
                    $state->next_warning_at = now()->addDays($rules['warning_interval_days']);
                    $state->decay_at = now()->addDays($rules['warning_decay_days']);
                    $this->event($user, 'warning', sprintf('HnR warning %d: %d torrents are overdue. Resume seeding before the next review.', $state->warnings, $overdue));
                }
            }
            $state->save();
        });
    }

    public function event(User $user, string $type, string $message): void
    {
        DB::table('hnr_events')->insert(['user_id' => $user->id, 'type' => $type, 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
    }
}
