<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_UNIQUE_INDEX = 'peers_torrent_id_peer_id_unique';

    private const NEW_UNIQUE_INDEX = 'peers_torrent_id_user_id_peer_id_unique';

    public function up(): void
    {
        if (! $this->canUpdatePeersIndexes()) {
            return;
        }

        Schema::table('peers', function (Blueprint $table): void {
            if ($this->hasIndex(self::OLD_UNIQUE_INDEX)) {
                $table->dropUnique(self::OLD_UNIQUE_INDEX);
            }

            if (! $this->hasIndex(self::NEW_UNIQUE_INDEX)) {
                $table->unique(['torrent_id', 'user_id', 'peer_id'], self::NEW_UNIQUE_INDEX);
            }
        });
    }

    public function down(): void
    {
        if (! $this->canUpdatePeersIndexes()) {
            return;
        }

        Schema::table('peers', function (Blueprint $table): void {
            if ($this->hasIndex(self::NEW_UNIQUE_INDEX)) {
                $table->dropUnique(self::NEW_UNIQUE_INDEX);
            }

            if (! $this->hasIndex(self::OLD_UNIQUE_INDEX)) {
                $table->unique(['torrent_id', 'peer_id'], self::OLD_UNIQUE_INDEX);
            }
        });
    }

    private function canUpdatePeersIndexes(): bool
    {
        return Schema::hasTable('peers')
            && Schema::hasColumn('peers', 'torrent_id')
            && Schema::hasColumn('peers', 'user_id')
            && Schema::hasColumn('peers', 'peer_id');
    }

    private function hasIndex(string $name): bool
    {
        /** @var list<array{name?: string}> $indexes */
        $indexes = Schema::getIndexes('peers');

        foreach ($indexes as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
