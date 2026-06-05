<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If there is no users table (for example in an empty test DB), do nothing
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Avoid errors if the column already exists
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
            // Only try to drop the column if it actually exists
            if (Schema::hasColumn('users', 'passkey')) {
                $table->dropColumn('passkey');
            }
        });
    }
};
