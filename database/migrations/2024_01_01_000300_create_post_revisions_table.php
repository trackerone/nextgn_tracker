<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('post_revisions')) {
            return;
        }

        Schema::create('post_revisions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('body_md');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('post_revisions')) {
            Schema::drop('post_revisions');
        }
    }
};
