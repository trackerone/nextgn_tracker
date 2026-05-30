<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torrent_watch_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('torrent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_watch_preset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
            $table->unique(['user_id', 'torrent_id', 'notification_watch_preset_id'], 'torrent_watch_unique_match');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torrent_watch_notifications');
    }
};
