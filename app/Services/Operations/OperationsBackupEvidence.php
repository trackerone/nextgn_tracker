<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operator-supplied evidence: artifact checks are not a substitute for restore drills.
 * File paths and checksums are deliberately never persisted or rendered in the UI.
 */
final class OperationsBackupEvidence
{
    /**
     * @return array<int, array{id:string,label:string,status:string,details:string}>
     */
    public function checks(): array
    {
        try {
            $records = DB::table('ops_backup_evidence')
                ->orderByDesc('observed_at')->limit(50)->get()
                ->unique('component')->keyBy('component');

            $missing = [];
            $stale = [];
            $verified = [];
            $offsite = true;

            foreach (['database', 'storage', 'secrets'] as $component) {
                $row = $records->get($component);

                if ($row === null) {
                    $missing[] = $component;

                    continue;
                }
                if ((string) $row->verification !== 'sha256_matched') {
                    $missing[] = $component.' (checksum unverified)';

                    continue;
                }

                if (Carbon::parse((string) $row->observed_at)->lt(now()->subHours(36))) {
                    $stale[] = $component;
                }
                $offsite = $offsite && (bool) $row->offsite_attested;
                $verified[] = $component;
            }

            if ($missing !== []) {
                $backup = $this->check('backups', 'Backups', 'unknown', 'Missing verified artifacts: '.implode(', ', $missing));
            } elseif ($stale !== []) {
                $backup = $this->check('backups', 'Backups', 'degraded', 'Verified artifacts older than 36h: '.implode(', ', $stale));
            } elseif ($offsite === false) {
                $backup = $this->check('backups', 'Backups', 'degraded', 'Backup artifacts verified; offsite copies not yet attested.');
            } else {
                $backup = $this->check('backups', 'Backups', 'healthy', 'Database, storage and secrets checksums verified within 36h; offsite copy attested.');
            }

            $restore = $records->get('restore');

            if ($restore === null || (string) $restore->verification !== 'operator_attested') {
                $drill = $this->check('restore_drill', 'Restore drill', 'unknown', 'No independently executed restore drill attested.');
            } elseif (Carbon::parse((string) $restore->observed_at)->lt(now()->subDays(31))) {
                $drill = $this->check('restore_drill', 'Restore drill', 'degraded', 'Last operator-attested restore drill is older than 31 days.');
            } else {
                $drill = $this->check('restore_drill', 'Restore drill', 'healthy', 'Operator-attested restore drill: '.$restore->observed_at.' (not independently reproduced by NextGN).');
            }

            return [$backup, $drill];
        } catch (Throwable) {
            return [
                $this->check('backups', 'Backups', 'unknown', 'Backup evidence is not available.'),
                $this->check('restore_drill', 'Restore drill', 'unknown', 'Restore evidence is not available.'),
            ];
        }
    }

    /**
     * @return array{id:string,label:string,status:string,details:string}
     */
    private function check(string $id, string $label, string $status, string $details): array
    {
        return compact('id', 'label', 'status', 'details');
    }
}
