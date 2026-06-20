<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alpha_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('area', 40);
            $table->string('severity', 40);
            $table->string('role', 80)->nullable();
            $table->string('environment', 120)->nullable();
            $table->string('title', 160);
            $table->text('steps_to_reproduce');
            $table->text('expected_result');
            $table->text('actual_result');
            $table->text('url_or_context')->nullable();
            $table->boolean('blocks_alpha')->default(false);
            $table->string('status', 40)->default('open');
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity', 'created_at']);
            $table->index(['area', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alpha_feedback');
    }
};
