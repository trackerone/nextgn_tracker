<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('torrents') || Schema::hasColumn('torrents', 'published_at')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('torrents') || ! Schema::hasColumn('torrents', 'published_at')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropColumn('published_at');
        });
    }
};
