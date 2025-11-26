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

        Schema::table('torrents', function (Blueprint $table): void {
            if (! Schema::hasColumn('torrents', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('categories')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('torrents')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            if (Schema::hasColumn('torrents', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });
    }
};
