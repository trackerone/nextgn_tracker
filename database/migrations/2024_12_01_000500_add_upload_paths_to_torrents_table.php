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
            if (! Schema::hasColumn('torrents', 'storage_path')) {
                $table->string('storage_path', 255)->nullable()->after('info_hash');
            }

            if (! Schema::hasColumn('torrents', 'nfo_storage_path')) {
                $table->string('nfo_storage_path', 255)->nullable()->after('nfo_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            if (Schema::hasColumn('torrents', 'nfo_storage_path')) {
                $table->dropColumn('nfo_storage_path');
            }

            if (Schema::hasColumn('torrents', 'storage_path')) {
                $table->dropColumn('storage_path');
            }
        });
    }
};
