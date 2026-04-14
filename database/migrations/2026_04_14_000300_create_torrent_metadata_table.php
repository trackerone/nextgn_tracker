<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torrent_metadata', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('torrent_id')->unique()->constrained('torrents')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('type', 32)->nullable();
            $table->string('resolution', 32)->nullable();
            $table->string('source', 64)->nullable();
            $table->string('release_group', 64)->nullable();
            $table->string('imdb_id', 16)->nullable();
            $table->string('imdb_url')->nullable();
            $table->unsignedBigInteger('tmdb_id')->nullable();
            $table->string('tmdb_url')->nullable();
            $table->longText('nfo')->nullable();
            $table->string('raw_name')->nullable();
            $table->string('parsed_name')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('imdb_id');
            $table->index('tmdb_id');
            $table->index(['type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torrent_metadata');
    }
};
