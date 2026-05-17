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
        // Ensure the new fields exist
        Schema::table('torrents', function (Blueprint $table): void {
            if (! Schema::hasColumn('torrents', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')
                    ->default(0)
                    ->after('info_hash');
            }

            if (! Schema::hasColumn('torrents', 'file_count')) {
                $table->unsignedInteger('file_count')
                    ->default(1)
                    ->after('size_bytes');
            }

            if (! Schema::hasColumn('torrents', 'files_count')) {
                $table->unsignedInteger('files_count')
                    ->default(1)
                    ->after('file_count');
            }

            if (! Schema::hasColumn('torrents', 'type')) {
                $table->string('type', 32)
                    ->default('other')
                    ->index()
                    ->after('files_count');
            }
        });

        // Backfill from old fields if they exist (prod/legacy),
        // but skip entirely in fresh environments (for example SQLite tests)
        if (Schema::hasColumn('torrents', 'size')) {
            DB::table('torrents')
                ->where('size_bytes', 0)
                ->update([
                    'size_bytes' => DB::raw('COALESCE(size, 0)'),
                ]);
        }

        if (Schema::hasColumn('torrents', 'numfiles')) {
            DB::table('torrents')
                ->where('file_count', 1)
                ->update([
                    'file_count' => DB::raw('COALESCE(numfiles, 1)'),
                ]);
        }

        // Drop legacy fields if they exist
        if (Schema::hasColumn('torrents', 'size')) {
            Schema::table('torrents', function (Blueprint $table): void {
                $table->dropColumn('size');
            });
        }

        if (Schema::hasColumn('torrents', 'numfiles')) {
            Schema::table('torrents', function (Blueprint $table): void {
                $table->dropColumn('numfiles');
            });
        }
    }

    public function down(): void
    {
        // Simple down: remove the new fields again if they exist
        Schema::table('torrents', function (Blueprint $table): void {
            if (Schema::hasColumn('torrents', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('torrents', 'files_count')) {
                $table->dropColumn('files_count');
            }

            if (Schema::hasColumn('torrents', 'file_count')) {
                $table->dropColumn('file_count');
            }

            if (Schema::hasColumn('torrents', 'size_bytes')) {
                $table->dropColumn('size_bytes');
            }
        });

        // (Do not recreate the legacy fields here; tests do not need them)
    }
};
