<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Place it roughly where it makes sense relative to the other columns
            $table->boolean('is_staff')
                ->default(false)
                ->index()
                ->after('is_disabled'); // if the column exists; otherwise remove ->after(...)
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_staff');
        });
    }
};
