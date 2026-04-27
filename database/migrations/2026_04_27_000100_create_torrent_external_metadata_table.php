<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torrent_external_metadata', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('torrent_id')->unique()->constrained('torrents')->cascadeOnDelete();
            $table->string('imdb_id', 16)->nullable();
            $table->string('tmdb_id')->nullable();
            $table->string('trakt_id')->nullable();
            $table->string('trakt_slug')->nullable();
            $table->string('title')->nullable();
            $table->string('original_title')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('media_type', 32)->nullable();
            $table->text('overview')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('backdrop_url')->nullable();
            $table->string('tmdb_url')->nullable();
            $table->string('imdb_url')->nullable();
            $table->string('trakt_url')->nullable();
            $table->json('providers_payload')->nullable();
            $table->timestamp('enriched_at')->nullable();
            $table->string('enrichment_status', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('imdb_id');
            $table->index('tmdb_id');
            $table->index('trakt_id');
            $table->index('enrichment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torrent_external_metadata');
    }
};
