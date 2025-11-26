<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'last_announce_at')) {
                $table->timestamp('last_announce_at')->nullable()->after('passkey');
            }

            if (! Schema::hasColumn('users', 'announce_rate_limit_exceeded')) {
                $table->boolean('announce_rate_limit_exceeded')->default(false)->after('last_announce_at');
            }
        });

        $this->ensureUserPasskeys();
        $this->enforcePasskeyNotNull();

        if (! Schema::hasTable('peers')) {
            return;
        }

        Schema::table('peers', function (Blueprint $table): void {
            if (! Schema::hasColumn('peers', 'client')) {
                $table->string('client', 64)->default('')->after('peer_id');
            }

            if (! Schema::hasColumn('peers', 'is_banned_client')) {
                $table->boolean('is_banned_client')->default(false)->after('client');
            }

            if (! Schema::hasColumn('peers', 'spoof_score')) {
                $table->unsignedInteger('spoof_score')->default(0)->after('is_banned_client');
            }

            if (! Schema::hasColumn('peers', 'last_action')) {
                $table->string('last_action', 20)->nullable()->after('left');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('peers')) {
            return;
        }

        Schema::table('peers', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('peers', 'client')) {
                $columns[] = 'client';
            }

            if (Schema::hasColumn('peers', 'is_banned_client')) {
                $columns[] = 'is_banned_client';
            }

            if (Schema::hasColumn('peers', 'spoof_score')) {
                $columns[] = 'spoof_score';
            }

            if (Schema::hasColumn('peers', 'last_action')) {
                $columns[] = 'last_action';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('users', 'last_announce_at')) {
                $columns[] = 'last_announce_at';
            }

            if (Schema::hasColumn('users', 'announce_rate_limit_exceeded')) {
                $columns[] = 'announce_rate_limit_exceeded';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        $this->allowNullablePasskey();
    }

    private function ensureUserPasskeys(): void
    {
        DB::table('users')
            ->whereNull('passkey')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['passkey' => bin2hex(random_bytes(32))]);
            });
    }

    private function enforcePasskeyNotNull(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY passkey CHAR(64) NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN passkey SET NOT NULL');

            return;
        }

        // TODO: support other drivers when needed.
    }

    private function allowNullablePasskey(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY passkey CHAR(64) NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN passkey DROP NOT NULL');

            return;
        }

        // TODO: support other drivers when needed.
    }
};
