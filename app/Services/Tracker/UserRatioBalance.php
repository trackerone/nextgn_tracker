<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Models\User;
use App\Models\UserRatioCredit;
use App\Models\UserStat;
use App\Services\Logging\AuditLogger;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserRatioBalance
{
    public const DEFAULT_UPLOADED_GIB = 'tracker.ratio.default_uploaded_gib';

    public const DEFAULT_DOWNLOADED_GIB = 'tracker.ratio.default_downloaded_gib';

    private const BYTES_PER_GIB = 1_073_741_824;

    public function __construct(private readonly SiteSettingsRepository $settings) {}

    /**
     * Effective ratio uses integrity-credited tracker traffic when available,
     * falling back to historical user_torrents for legacy accounts. Sysop
     * credits are separate and never alter actual transfer or HnR history.
     *
     * @return array{uploaded: int, downloaded: int, ratio: float|null}
     */
    public function totals(User $user): array
    {
        $actual = UserStat::query()->where('user_id', $user->getKey())->first();

        if ($actual === null || ((int) $actual->uploaded_bytes === 0 && (int) $actual->downloaded_bytes === 0)) {
            $traffic = $user->userTorrents()
                ->selectRaw('COALESCE(SUM(uploaded), 0) AS total_uploaded, COALESCE(SUM(downloaded), 0) AS total_downloaded')
                ->first();
            $uploaded = (int) data_get($traffic, 'total_uploaded', 0);
            $downloaded = (int) data_get($traffic, 'total_downloaded', 0);
        } else {
            $uploaded = (int) $actual->uploaded_bytes;
            $downloaded = (int) $actual->downloaded_bytes;
        }

        $credit = UserRatioCredit::query()->where('user_id', $user->getKey())->first();
        $uploaded = max(0, $uploaded + (int) ($credit->uploaded_credit_bytes ?? 0));
        $downloaded = max(0, $downloaded + (int) ($credit->downloaded_credit_bytes ?? 0));

        return [
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
            'ratio' => $downloaded > 0 ? $uploaded / $downloaded : null,
        ];
    }

    /**
     * @return array{uploaded: float, downloaded: float}
     */
    public function registrationDefaults(): array
    {
        return [
            'uploaded' => max(0, $this->settings->getFloat(self::DEFAULT_UPLOADED_GIB)),
            'downloaded' => max(0, $this->settings->getFloat(self::DEFAULT_DOWNLOADED_GIB)),
        ];
    }

    public function initializeForRegistration(User $user): void
    {
        $defaults = $this->registrationDefaults();
        $upload = self::toBytes($defaults['uploaded']);
        $download = self::toBytes($defaults['downloaded']);

        if ($upload === 0 && $download === 0) {
            return;
        }

        UserRatioCredit::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['uploaded_credit_bytes' => $upload, 'downloaded_credit_bytes' => $download],
        );
    }

    public static function toBytes(float $gib): int
    {
        return (int) round($gib * self::BYTES_PER_GIB);
    }

    public function adjust(
        User $actor,
        User $target,
        string $metric,
        string $direction,
        float $amountGib,
        string $reason,
        AuditLogger $audit,
    ): void {
        $column = match ($metric) {
            'uploaded' => 'uploaded_credit_bytes',
            'downloaded' => 'downloaded_credit_bytes',
            default => throw new \InvalidArgumentException('Unknown ratio metric.'),
        };

        if (! in_array($direction, ['add', 'subtract'], true)) {
            throw new \InvalidArgumentException('Unknown ratio adjustment direction.');
        }

        $bytes = self::toBytes($amountGib);
        if ($bytes < 1) {
            throw ValidationException::withMessages(['amount_gib' => 'Adjustment must be greater than zero.']);
        }

        DB::transaction(function () use ($target, $actor, $column, $metric, $direction, $bytes, $reason, $audit): void {
            UserRatioCredit::query()->insertOrIgnore([
                'user_id' => $target->getKey(),
                'uploaded_credit_bytes' => 0,
                'downloaded_credit_bytes' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $credit = UserRatioCredit::query()->where('user_id', $target->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->totals($target);
            $signed = $direction === 'add' ? $bytes : -$bytes;

            if ($signed < 0 && $before[$metric] < $bytes) {
                throw ValidationException::withMessages(['amount_gib' => 'Cannot subtract more than the current effective balance.']);
            }

            $credit->{$column} += $signed;
            $credit->save();

            $audit->log('sysop.ratio.credit_adjusted', $target, [
                'actor_user_id' => $actor->getKey(),
                'target_user_id' => $target->getKey(),
                'metric' => $metric,
                'direction' => $direction,
                'delta_bytes' => $signed,
                'previous_balance' => $before,
                'new_balance' => $this->totals($target),
                'reason' => $reason,
            ]);
        });
    }
}
