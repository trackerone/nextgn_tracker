<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hnr_obligations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('torrent_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('downloaded_bytes')->default(0);
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->unsignedBigInteger('seed_seconds')->default(0);
            $table->unsignedInteger('required_seed_seconds');
            $table->decimal('required_ratio', 8, 3);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('last_counted_at');
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'torrent_id']);
            $table->index(['user_id', 'fulfilled_at', 'waived_at']);
        });
        Schema::create('hnr_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('warnings')->default(0);
            $table->timestamp('next_warning_at')->nullable();
            $table->timestamp('decay_at')->nullable();
            $table->timestamp('restricted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('hnr_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('message');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hnr_events');
        Schema::dropIfExists('hnr_accounts');
        Schema::dropIfExists('hnr_obligations');
    }
};
