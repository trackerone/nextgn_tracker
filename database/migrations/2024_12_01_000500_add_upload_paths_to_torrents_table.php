<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('torrents')) {
            return;
        }

        $hasInfoHash = Schema::hasColumn('torrents', 'info_hash');
        $hasNfoText = Schema::hasColumn('torrents', 'nfo_text');

        Schema::table('torrents', function (Blueprint $table) use ($hasInfoHash, $hasNfoText): void {
            if (! Schema::hasColumn('torrents', 'storage_path')) {
                $col = $table->string('storage_path', 255)->nullable();

                if ($hasInfoHash) {
                    $col->after('info_hash');
                }
            }

            if (! Schema::hasColumn('torrents', 'nfo_storage_path')) {
                $col = $table->string('nfo_storage_path', 255)->nullable();

                if ($hasNfoText) {
                    $col->after('nfo_text');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('torrents')) {
            return;
        }

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
