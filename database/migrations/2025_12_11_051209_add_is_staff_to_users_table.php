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
            // Læg dig ca. dér hvor det giver mening ift. resten af kolonnerne
            $table->boolean('is_staff')
                ->default(false)
                ->index()
                ->after('is_disabled'); // hvis kolonnen findes – ellers fjern ->after(...)
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_staff');
        });
    }
};
