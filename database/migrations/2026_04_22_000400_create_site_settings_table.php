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
        if (Schema::hasTable('site_settings')) {
            return;
        }

        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('type');
            $table->string('group')->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('site_settings')->insert([
            [
                'key' => 'tracker.ratio.enforcement_enabled',
                'value' => 'true',
                'type' => 'bool',
                'group' => 'tracker_ratio',
                'label' => 'Ratio enforcement enabled',
                'description' => 'Enable minimum ratio enforcement before torrent downloads.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'tracker.ratio.minimum_download_ratio',
                'value' => '0.5',
                'type' => 'float',
                'group' => 'tracker_ratio',
                'label' => 'Minimum download ratio',
                'description' => 'Minimum upload/download ratio required to download torrents.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'tracker.ratio.freeleech_bypass_enabled',
                'value' => 'true',
                'type' => 'bool',
                'group' => 'tracker_ratio',
                'label' => 'Freeleech bypass enabled',
                'description' => 'Allow downloads of freeleech torrents regardless of ratio.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'tracker.ratio.no_history_grace_enabled',
                'value' => 'true',
                'type' => 'bool',
                'group' => 'tracker_ratio',
                'label' => 'No history grace enabled',
                'description' => 'Allow downloads for users without any recorded ratio history.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
