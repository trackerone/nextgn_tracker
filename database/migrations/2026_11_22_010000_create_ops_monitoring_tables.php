<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_job_status', function (Blueprint $table): void {
            $table->string('job_key', 100)->primary();
            $table->string('state', 24)->default('unknown');
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->unsignedInteger('last_duration_ms')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('ops_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 120)->unique();
            $table->string('component', 80);
            $table->string('title', 160);
            $table->string('severity', 16);
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'last_seen_at']);
        });

        Schema::create('ops_backup_evidence', function (Blueprint $table): void {
            $table->id();
            $table->string('component', 32);
            $table->string('verification', 24);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('offsite_attested')->default(false);
            $table->timestamp('observed_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['component', 'observed_at']);
        });

        Schema::create('ops_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('captured_at')->index();
            $table->string('overall_status', 16);
            $table->unsignedInteger('tracker_announces')->nullable();
            $table->unsignedInteger('tracker_rejected')->nullable();
            $table->unsignedInteger('tracker_scrapes')->nullable();
            $table->unsignedInteger('http_5xx')->nullable();
            $table->unsignedBigInteger('disk_free_bytes')->nullable();
            $table->unsignedInteger('database_latency_ms')->nullable();
            $table->unsignedInteger('seeders')->nullable();
            $table->unsignedInteger('leechers')->nullable();
            $table->unsignedBigInteger('host_memory_total_bytes')->nullable();
            $table->unsignedBigInteger('host_memory_available_bytes')->nullable();
            $table->decimal('cpu_load_1m', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_snapshots');
        Schema::dropIfExists('ops_backup_evidence');
        Schema::dropIfExists('ops_alerts');
        Schema::dropIfExists('ops_job_status');
    }
};
