<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_stats')) {
            return;
        }

        Schema::create('user_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->unsignedBigInteger('downloaded_bytes')->default(0);
            $table->unsignedBigInteger('seed_time_seconds')->default(0);
            $table->unsignedBigInteger('leech_time_seconds')->default(0);
            $table->unsignedInteger('completed_torrents_count')->default(0);
            $table->timestamp('last_announced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('user_stats')) {
            Schema::drop('user_stats');
        }
    }
};
