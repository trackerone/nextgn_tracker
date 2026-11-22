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
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('media_type', 24)->default('other');
            $table->boolean('is_active')->default(true);
        });

        // Existing default rows stay correctly classified, including Books.
        foreach ([
            'movies' => 'movie',
            'tv' => 'tv',
            'music' => 'music',
            'games' => 'game',
            'software' => 'software',
            'books' => 'book',
        ] as $slug => $type) {
            DB::table('categories')->where('slug', $slug)->update(['media_type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['media_type', 'is_active']);
        });
    }
};
