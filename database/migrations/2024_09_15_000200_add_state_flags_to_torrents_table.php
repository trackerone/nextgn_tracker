<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->boolean('is_approved')->default(true)->after('is_visible');
            $table->boolean('is_banned')->default(false)->after('is_approved');
            $table->string('ban_reason', 255)->nullable()->after('is_banned');
            $table->boolean('freeleech')->default(false)->after('ban_reason');
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropColumn(['is_approved', 'is_banned', 'ban_reason', 'freeleech']);
        });
    }
};
