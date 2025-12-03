<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('torrents', 'type')) {
            Schema::table('torrents', function (Blueprint $table): void {
                // placeret logisk efter files_count – 'after' ignoreres i SQLite, så det er safe
                $table->string('type', 32)
                    ->default('other')
                    ->index()
                    ->after('files_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('torrents', 'type')) {
            Schema::table('torrents', function (Blueprint $table): void {
                $table->dropColumn('type');
            });
        }
    }
};
