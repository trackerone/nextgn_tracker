<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_torrents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('torrent_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('uploaded')->default(0);
            $table->unsignedBigInteger('downloaded')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_announce_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'torrent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_torrents');
    }
};
