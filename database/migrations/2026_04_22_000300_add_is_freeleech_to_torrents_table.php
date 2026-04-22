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
        if (! Schema::hasTable('torrents') || Schema::hasColumn('torrents', 'is_freeleech')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            $table->boolean('is_freeleech')->default(false)->after('freeleech');
        });

        if (Schema::hasColumn('torrents', 'freeleech')) {
            DB::table('torrents')->update([
                'is_freeleech' => DB::raw('freeleech'),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('torrents') || ! Schema::hasColumn('torrents', 'is_freeleech')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropColumn('is_freeleech');
        });
    }
};

