<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torrent_follows', function (Blueprint $table): void {
            $table->timestamp('last_checked_at')->nullable()->after('year');
            $table->index(['user_id', 'last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('torrent_follows', function (Blueprint $table): void {
            $table->dropIndex('torrent_follows_user_id_last_checked_at_index');
            $table->dropColumn('last_checked_at');
        });
    }
};
