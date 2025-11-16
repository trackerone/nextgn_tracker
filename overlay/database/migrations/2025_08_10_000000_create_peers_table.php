<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peers', function (Blueprint $table): void {
            $table->id();
            $table->string('info_hash', 40)->index();
            $table->string('peer_id', 40);
            $table->string('ip', 45);
            $table->unsignedInteger('port');
            $table->unsignedBigInteger('uploaded')->default(0);
            $table->unsignedBigInteger('downloaded')->default(0);
            $table->unsignedBigInteger('left_bytes')->default(0);
            $table->string('event', 16)->nullable();
            $table->timestamp('last_announce')->index();
            $table->unique(['info_hash', 'peer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peers');
    }
};
