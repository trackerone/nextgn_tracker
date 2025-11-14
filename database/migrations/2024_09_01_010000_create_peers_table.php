<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('peers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('torrent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('peer_id', 20);
            $table->string('ip', 45);
            $table->unsignedInteger('port');
            $table->unsignedBigInteger('uploaded')->default(0);
            $table->unsignedBigInteger('downloaded')->default(0);
            $table->unsignedBigInteger('left')->default(0);
            $table->boolean('is_seeder')->default(false);
            $table->timestamp('last_announce_at');
            $table->timestamps();

            $table->unique(['torrent_id', 'peer_id']);
            $table->index(['torrent_id', 'is_seeder']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peers');
    }
};
