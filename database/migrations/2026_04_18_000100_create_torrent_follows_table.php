<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torrent_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('normalized_title');
            $table->string('type', 32)->nullable();
            $table->string('resolution', 32)->nullable();
            $table->string('source', 64)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'normalized_title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torrent_follows');
    }
};
