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
            if (! Schema::hasColumn('torrents', 'description')) {
                $table->text('description')->nullable()->after('freeleech');
            }

            if (! Schema::hasColumn('torrents', 'original_filename')) {
                $table->string('original_filename')->nullable()->after('description');
            }

            if (! Schema::hasColumn('torrents', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('original_filename');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('torrents')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('torrents', 'description')) {
                $columns[] = 'description';
            }

            if (Schema::hasColumn('torrents', 'original_filename')) {
                $columns[] = 'original_filename';
            }

            if (Schema::hasColumn('torrents', 'uploaded_at')) {
                $columns[] = 'uploaded_at';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
