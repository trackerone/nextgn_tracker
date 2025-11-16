<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            if (!Schema::hasColumn('torrents', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')->default(0)->after('info_hash');
            }

            if (!Schema::hasColumn('torrents', 'file_count')) {
                $table->unsignedInteger('file_count')->default(0)->after('size_bytes');
            }

            if (!Schema::hasColumn('torrents', 'type')) {
                $table->string('type', 20)->default('other')->after('file_count');
            }

            if (!Schema::hasColumn('torrents', 'source')) {
                $table->string('source', 50)->nullable()->after('type');
            }

            if (!Schema::hasColumn('torrents', 'resolution')) {
                $table->string('resolution', 20)->nullable()->after('source');
            }

            if (!Schema::hasColumn('torrents', 'codecs')) {
                $table->json('codecs')->nullable()->after('resolution');
            }

            if (!Schema::hasColumn('torrents', 'tags')) {
                $table->json('tags')->nullable()->after('codecs');
            }

            if (!Schema::hasColumn('torrents', 'nfo_text')) {
                $table->longText('nfo_text')->nullable()->after('description');
            }

            if (!Schema::hasColumn('torrents', 'imdb_id')) {
                $table->string('imdb_id', 16)->nullable()->after('nfo_text');
            }

            if (!Schema::hasColumn('torrents', 'tmdb_id')) {
                $table->string('tmdb_id', 16)->nullable()->after('imdb_id');
            }
        });

        DB::statement('UPDATE torrents SET size_bytes = COALESCE(size, 0) WHERE size_bytes = 0');
        DB::statement('UPDATE torrents SET file_count = COALESCE(files_count, 0) WHERE file_count = 0');
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            if (Schema::hasColumn('torrents', 'tmdb_id')) {
                $table->dropColumn('tmdb_id');
            }

            if (Schema::hasColumn('torrents', 'imdb_id')) {
                $table->dropColumn('imdb_id');
            }

            if (Schema::hasColumn('torrents', 'nfo_text')) {
                $table->dropColumn('nfo_text');
            }

            if (Schema::hasColumn('torrents', 'tags')) {
                $table->dropColumn('tags');
            }

            if (Schema::hasColumn('torrents', 'codecs')) {
                $table->dropColumn('codecs');
            }

            if (Schema::hasColumn('torrents', 'resolution')) {
                $table->dropColumn('resolution');
            }

            if (Schema::hasColumn('torrents', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('torrents', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('torrents', 'file_count')) {
                $table->dropColumn('file_count');
            }

            if (Schema::hasColumn('torrents', 'size_bytes')) {
                $table->dropColumn('size_bytes');
            }
        });
    }
};
