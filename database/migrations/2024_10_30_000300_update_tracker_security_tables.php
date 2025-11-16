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
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_announce_at')->nullable()->after('passkey');
            $table->boolean('announce_rate_limit_exceeded')->default(false)->after('last_announce_at');
        });

        $this->ensureUserPasskeys();
        $this->enforcePasskeyNotNull();

        Schema::table('peers', function (Blueprint $table): void {
            $table->string('client', 64)->default('')->after('peer_id');
            $table->boolean('is_banned_client')->default(false)->after('client');
            $table->unsignedInteger('spoof_score')->default(0)->after('is_banned_client');
            $table->string('last_action', 20)->nullable()->after('left');
        });
    }

    public function down(): void
    {
        Schema::table('peers', function (Blueprint $table): void {
            $table->dropColumn(['client', 'is_banned_client', 'spoof_score', 'last_action']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['last_announce_at', 'announce_rate_limit_exceeded']);
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
