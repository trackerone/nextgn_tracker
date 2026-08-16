<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('peers') || ! Schema::hasColumn('peers', 'peer_id')) {
            return;
        }

        if (in_array(Schema::getColumnType('peers', 'peer_id'), ['binary', 'blob'], true)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE peers MODIFY peer_id VARBINARY(20) NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE peers ALTER COLUMN peer_id TYPE BYTEA USING convert_to(peer_id, 'UTF8')");

            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE peers ALTER COLUMN peer_id VARBINARY(20) NOT NULL');
        }
    }

    public function down(): void
    {
        // Peer IDs are arbitrary protocol bytes. Converting them back to a text
        // column can corrupt existing values, so this storage correction is
        // intentionally irreversible.
    }
};
