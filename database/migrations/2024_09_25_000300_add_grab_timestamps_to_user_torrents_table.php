<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_torrents', static function (Blueprint $table): void {
            $table->timestamp('first_grab_at')->nullable()->after('last_announce_at');
            $table->timestamp('last_grab_at')->nullable()->after('first_grab_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_torrents', static function (Blueprint $table): void {
            $table->dropColumn(['first_grab_at', 'last_grab_at']);
        });
    }
};
