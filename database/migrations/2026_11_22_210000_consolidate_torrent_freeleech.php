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
        if (! Schema::hasTable('torrents') || ! Schema::hasColumn('torrents', 'freeleech')) {
            return;
        }

        // Preserve old rows that were marked freeleech only in the legacy column.
        // Already enabled canonical states remain enabled.
        DB::table('torrents')
            ->where('freeleech', true)
            ->update(['is_freeleech' => true]);

        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropColumn('freeleech');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('torrents') || Schema::hasColumn('torrents', 'freeleech')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            $table->boolean('freeleech')->default(false);
        });

        DB::table('torrents')->update(['freeleech' => DB::raw('is_freeleech')]);
    }
};
