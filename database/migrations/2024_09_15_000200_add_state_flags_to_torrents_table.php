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
            if (! Schema::hasColumn('torrents', 'is_approved')) {
                $table->boolean('is_approved')->default(true)->after('is_visible');
            }

            if (! Schema::hasColumn('torrents', 'is_banned')) {
                $table->boolean('is_banned')->default(false)->after('is_approved');
            }

            if (! Schema::hasColumn('torrents', 'ban_reason')) {
                $table->string('ban_reason', 255)->nullable()->after('is_banned');
            }

            if (! Schema::hasColumn('torrents', 'freeleech')) {
                $table->boolean('freeleech')->default(false)->after('ban_reason');
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

            if (Schema::hasColumn('torrents', 'is_approved')) {
                $columns[] = 'is_approved';
            }

            if (Schema::hasColumn('torrents', 'is_banned')) {
                $columns[] = 'is_banned';
            }

            if (Schema::hasColumn('torrents', 'ban_reason')) {
                $columns[] = 'ban_reason';
            }

            if (Schema::hasColumn('torrents', 'freeleech')) {
                $columns[] = 'freeleech';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
