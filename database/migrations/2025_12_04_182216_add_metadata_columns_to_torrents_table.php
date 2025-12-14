<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            // Vi antager at følgende allerede findes fra tidligere migrationer:
            // status, category_id, type, file_count, source, osv.
            // Nu tilføjer vi resten af de felter, som INSERT-statementet forventer.

            if (! Schema::hasColumn('torrents', 'resolution')) {
                $table->string('resolution', 32)->nullable()->after('source');
            }

            if (! Schema::hasColumn('torrents', 'codecs')) {
                // JSON til fx {"video":"x264","audio":"DTS"}
                $table->json('codecs')->nullable()->after('resolution');
            }

            if (! Schema::hasColumn('torrents', 'tags')) {
                // Simpel tekststreng for tags (komma-separeret, el. lign.)
                $table->text('tags')->nullable()->after('codecs');
            }

            if (! Schema::hasColumn('torrents', 'description')) {
                $table->text('description')->nullable()->after('tags');
            }

            if (! Schema::hasColumn('torrents', 'nfo_text')) {
                $table->longText('nfo_text')->nullable()->after('description');
            }

            if (! Schema::hasColumn('torrents', 'nfo_storage_path')) {
                $table->string('nfo_storage_path')->nullable()->after('nfo_text');
            }

            if (! Schema::hasColumn('torrents', 'imdb_id')) {
                $table->string('imdb_id', 32)->nullable()->after('nfo_storage_path');
            }

            if (! Schema::hasColumn('torrents', 'tmdb_id')) {
                $table->unsignedBigInteger('tmdb_id')->nullable()->after('imdb_id');
            }

            if (! Schema::hasColumn('torrents', 'original_filename')) {
                $table->string('original_filename')->nullable()->after('tmdb_id');
            }

            if (! Schema::hasColumn('torrents', 'is_approved')) {
                $table->boolean('is_approved')->default(false)->after('original_filename');
            }

            if (! Schema::hasColumn('torrents', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('is_approved');
            }

            if (! Schema::hasColumn('torrents', 'storage_path')) {
                $table->string('storage_path')->nullable()->after('uploaded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $columns = [
                'resolution',
                'codecs',
                'tags',
                'description',
                'nfo_text',
                'nfo_storage_path',
                'imdb_id',
                'tmdb_id',
                'original_filename',
                'is_approved',
                'uploaded_at',
                'storage_path',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('torrents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
