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
            $table->text('description')->nullable()->after('freeleech');
            $table->string('original_filename')->nullable()->after('description');
            $table->timestamp('uploaded_at')->nullable()->after('original_filename');
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropColumn(['description', 'original_filename', 'uploaded_at']);
        });
    }
};
