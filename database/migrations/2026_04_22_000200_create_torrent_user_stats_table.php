<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('torrent_user_stats')) {
            return;
        }

        Schema::create('torrent_user_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('torrent_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->unsignedBigInteger('downloaded_bytes')->default(0);
            $table->unsignedBigInteger('seed_time_seconds')->default(0);
            $table->unsignedBigInteger('leech_time_seconds')->default(0);
            $table->unsignedInteger('times_completed')->default(0);
            $table->timestamp('first_completed_at')->nullable();
            $table->timestamp('last_completed_at')->nullable();
            $table->timestamp('last_announced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'torrent_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('torrent_user_stats')) {
            Schema::drop('torrent_user_stats');
        }
    }
};
