<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('torrents')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            if (! Schema::hasColumn('torrents', 'status')) {
                $table->string('status')->default('pending')->after('freeleech');
            }

            if (! Schema::hasColumn('torrents', 'moderated_by')) {
                $table->foreignId('moderated_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('torrents', 'moderated_at')) {
                $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            }

            if (! Schema::hasColumn('torrents', 'moderated_reason')) {
                $table->text('moderated_reason')->nullable()->after('moderated_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('torrents')) {
            return;
        }

        Schema::table('torrents', function (Blueprint $table): void {
            if (Schema::hasColumn('torrents', 'moderated_by')) {
                $table->dropForeign(['moderated_by']);
            }

            $columns = [];

            if (Schema::hasColumn('torrents', 'status')) {
                $columns[] = 'status';
            }

            if (Schema::hasColumn('torrents', 'moderated_by')) {
                $columns[] = 'moderated_by';
            }

            if (Schema::hasColumn('torrents', 'moderated_at')) {
                $columns[] = 'moderated_at';
            }

            if (Schema::hasColumn('torrents', 'moderated_reason')) {
                $columns[] = 'moderated_reason';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
