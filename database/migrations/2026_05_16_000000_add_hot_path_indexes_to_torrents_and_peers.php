<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const TORRENT_INDEXES = [
        'torrents_status_uploaded_idx' => ['status', 'uploaded_at'],
        'torrents_status_flags_uploaded_idx' => ['status', 'is_banned', 'is_approved', 'uploaded_at'],
        'torrents_category_status_uploaded_idx' => ['category_id', 'status', 'uploaded_at'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const PEER_INDEXES = [
        'peers_torrent_last_announce_idx' => ['torrent_id', 'last_announce_at'],
        'peers_user_last_announce_idx' => ['user_id', 'last_announce_at'],
        'peers_torrent_seeder_last_announce_idx' => ['torrent_id', 'is_seeder', 'last_announce_at'],
    ];

    public function up(): void
    {
        $this->addIndexes('torrents', self::TORRENT_INDEXES);
        $this->addIndexes('peers', self::PEER_INDEXES);
    }

    public function down(): void
    {
        $this->dropIndexes('peers', self::PEER_INDEXES);
        $this->dropIndexes('torrents', self::TORRENT_INDEXES);
    }

    /**
     * @param  array<string, list<string>>  $indexes
     */
    private function addIndexes(string $tableName, array $indexes): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexes): void {
            foreach ($indexes as $name => $columns) {
                if (
                    ! $this->hasColumns($tableName, $columns)
                    || $this->hasIndex($tableName, $name, $columns)
                ) {
                    continue;
                }

                $table->index($columns, $name);
            }
        });
    }

    /**
     * @param  array<string, list<string>>  $indexes
     */
    private function dropIndexes(string $tableName, array $indexes): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexes): void {
            foreach (array_keys($indexes) as $name) {
                if ($this->hasIndexNamed($tableName, $name)) {
                    $table->dropIndex($name);
                }
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasColumns(string $tableName, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasIndex(string $tableName, string $name, array $columns): bool
    {
        foreach ($this->indexes($tableName) as $index) {
            if (($index['name'] ?? null) === $name || ($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function hasIndexNamed(string $tableName, string $name): bool
    {
        foreach ($this->indexes($tableName) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{name?: string, columns?: list<string>}>
     */
    private function indexes(string $tableName): array
    {
        /** @var list<array{name?: string, columns?: list<string>}> $indexes */
        $indexes = Schema::getIndexes($tableName);

        return $indexes;
    }
};
