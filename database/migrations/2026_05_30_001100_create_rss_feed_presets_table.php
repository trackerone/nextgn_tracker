<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_feed_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_feed_presets');
    }
};
