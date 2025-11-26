<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hvis der ikke er nogen users-tabel (fx i en tom test-DB), så gør ingenting
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Undgå fejl hvis kolonnen allerede findes
            if (! Schema::hasColumn('users', 'passkey')) {
                $table
                    ->string('passkey', 64)
                    ->nullable()
                    ->unique()
                    ->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Kun prøv at droppe, hvis kolonnen faktisk findes
            if (Schema::hasColumn('users', 'passkey')) {
                $table->dropColumn('passkey');
            }
        });
    }
};
