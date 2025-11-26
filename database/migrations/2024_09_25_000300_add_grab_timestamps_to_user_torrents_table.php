<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_torrents')) {
            return;
        }

        Schema::table('user_torrents', static function (Blueprint $table): void {
            if (! Schema::hasColumn('user_torrents', 'first_grab_at')) {
                $table->timestamp('first_grab_at')->nullable()->after('last_announce_at');
            }

            if (! Schema::hasColumn('user_torrents', 'last_grab_at')) {
                $table->timestamp('last_grab_at')->nullable()->after('first_grab_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_torrents')) {
            return;
        }

        Schema::table('user_torrents', static function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('user_torrents', 'first_grab_at')) {
                $columns[] = 'first_grab_at';
            }

            if (Schema::hasColumn('user_torrents', 'last_grab_at')) {
                $columns[] = 'last_grab_at';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
