<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('torrents')) {
            return;
        }

        Schema::create('torrents', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('info_hash', 40)->unique();
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedInteger('seeders')->default(0);
            $table->unsignedInteger('leechers')->default(0);
            $table->unsignedInteger('completed')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('torrents')) {
            Schema::drop('torrents');
        }
    }
};
