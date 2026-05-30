<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torrent_metadata', function (Blueprint $table): void {
            $table->string('language', 32)->nullable()->after('release_group');
            $table->string('audio_language', 32)->nullable()->after('language');
            $table->string('subtitle_language', 32)->nullable()->after('audio_language');
            $table->string('subtitles')->nullable()->after('subtitle_language');
        });
    }

    public function down(): void
    {
        Schema::table('torrent_metadata', function (Blueprint $table): void {
            $table->dropColumn([
                'language',
                'audio_language',
                'subtitle_language',
                'subtitles',
            ]);
        });
    }
};
