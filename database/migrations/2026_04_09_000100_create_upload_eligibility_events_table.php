<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_eligibility_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('allowed');
            $table->string('reason', 64);
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['allowed', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_eligibility_events');
    }
};
