<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KEY_PREFIX_INDEX = 'api_keys_key_prefix_index';

    public function up(): void
    {
        if (! Schema::hasTable('api_keys')) {
            return;
        }

        Schema::table('api_keys', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_keys', 'key_prefix')) {
                $table->string('key_prefix', 16)->nullable()->after('key');
            }

            if (! Schema::hasColumn('api_keys', 'key_hash')) {
                $table->string('key_hash', 64)->nullable()->after('key_prefix');
            }

            if (! $this->hasIndex(self::KEY_PREFIX_INDEX)) {
                $table->index('key_prefix', self::KEY_PREFIX_INDEX);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('api_keys')) {
            return;
        }

        Schema::table('api_keys', function (Blueprint $table): void {
            if ($this->hasIndex(self::KEY_PREFIX_INDEX)) {
                $table->dropIndex(self::KEY_PREFIX_INDEX);
            }

            if (Schema::hasColumn('api_keys', 'key_hash')) {
                $table->dropColumn('key_hash');
            }

            if (Schema::hasColumn('api_keys', 'key_prefix')) {
                $table->dropColumn('key_prefix');
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        /** @var list<array{name?: string}> $indexes */
        $indexes = Schema::getIndexes('api_keys');

        foreach ($indexes as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
