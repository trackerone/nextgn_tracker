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
            // Kilde/scene-type: fx bluray, web, hdtv osv.
            // Ligger lige efter type for at matche INSERT-ordenen
            $table->string('source', 32)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
