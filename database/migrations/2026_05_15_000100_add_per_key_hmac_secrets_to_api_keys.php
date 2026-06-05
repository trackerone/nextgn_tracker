<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_keys')) {
            return;
        }

        Schema::table('api_keys', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_keys', 'hmac_secret_hash')) {
                $table->string('hmac_secret_hash', 64)->nullable()->after('key_hash');
            }

            if (! Schema::hasColumn('api_keys', 'hmac_secret_fingerprint')) {
                $table->string('hmac_secret_fingerprint', 16)->nullable()->after('hmac_secret_hash');
            }

            if (! Schema::hasColumn('api_keys', 'hmac_version')) {
                $table->string('hmac_version', 32)->nullable()->after('hmac_secret_fingerprint');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('api_keys')) {
            return;
        }

        Schema::table('api_keys', function (Blueprint $table): void {
            if (Schema::hasColumn('api_keys', 'hmac_version')) {
                $table->dropColumn('hmac_version');
            }

            if (Schema::hasColumn('api_keys', 'hmac_secret_fingerprint')) {
                $table->dropColumn('hmac_secret_fingerprint');
            }

            if (Schema::hasColumn('api_keys', 'hmac_secret_hash')) {
                $table->dropColumn('hmac_secret_hash');
            }
        });
    }
};
