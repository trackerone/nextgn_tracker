<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RecordOperationsBackupEvidenceCommand extends Command
{
    protected $signature = 'ops:backup-evidence
        {component : database, storage, secrets, or restore}
        {artifact : Absolute path to an existing backup artifact or restore drill report}
        {--sha256= : Expected SHA256 checksum of artifact, required for backup verification}
        {--offsite : Operator confirms the verified artifact has an offsite copy}
        {--attest-restore : Operator confirms an isolated restore drill was completed}';

    protected $description = 'Record verified backup artifacts or a separately attested restore drill (CLI-only).';

    public function handle(): int
    {
        $component = (string) $this->argument('component');
        if (in_array($component, ['database', 'storage', 'secrets', 'restore'], true) === false) {
            $this->error('Invalid component.');

            return self::FAILURE;
        }

        $root = (string) config('operations.backup_root', '');
        $rootReal = realpath($root);
        $artifact = realpath((string) $this->argument('artifact'));

        if ($root === '' || $rootReal === false || $artifact === false
            || str_starts_with($artifact, rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) === false
            || is_file($artifact) === false || is_readable($artifact) === false || filesize($artifact) < 1
        ) {
            $this->error('Artifact must be a readable nonempty file beneath configured OPS_BACKUP_ROOT.');

            return self::FAILURE;
        }

        $expected = strtolower(trim((string) $this->option('sha256')));
        if ($component !== 'restore' && preg_match('/\A[a-f0-9]{64}\z/', $expected) !== 1) {
            $this->error('A 64-character --sha256 checksum is required for artifact integrity verification.');

            return self::FAILURE;
        }

        if ($component === 'restore' && (bool) $this->option('attest-restore') === false) {
            $this->error('A restore record requires --attest-restore after an isolated restore drill.');

            return self::FAILURE;
        }

        if ($component !== 'restore') {
            $actual = hash_file('sha256', $artifact);
            if ($actual === false || hash_equals($expected, $actual) === false) {
                $this->error('Backup checksum validation failed.');

                return self::FAILURE;
            }
        }

        DB::table('ops_backup_evidence')->insert([
            'component' => $component,
            'verification' => $component === 'restore' ? 'operator_attested' : 'sha256_matched',
            'size_bytes' => filesize($artifact),
            'offsite_attested' => $component !== 'restore' && (bool) $this->option('offsite'),
            'observed_at' => now(),
            'created_at' => now(),
        ]);

        $this->info($component === 'restore'
            ? 'Operator restore-drill attestation recorded (not independently reproduced by NextGN).'
            : 'Artifact SHA256 matched and backup evidence recorded.');

        return self::SUCCESS;
    }
}
