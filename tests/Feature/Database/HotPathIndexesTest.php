<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class HotPathIndexesTest extends TestCase
{
    public function test_hot_path_indexes_exist_for_torrents_and_peers(): void
    {
        $this->assertIndexExists('torrents', 'torrents_status_uploaded_idx', ['status', 'uploaded_at']);
        $this->assertIndexExists(
            'torrents',
            'torrents_status_flags_uploaded_idx',
            ['status', 'is_banned', 'is_approved', 'uploaded_at'],
        );
        $this->assertIndexExists(
            'torrents',
            'torrents_category_status_uploaded_idx',
            ['category_id', 'status', 'uploaded_at'],
        );

        $this->assertIndexExists('peers', 'peers_torrent_last_announce_idx', ['torrent_id', 'last_announce_at']);
        $this->assertIndexExists('peers', 'peers_user_last_announce_idx', ['user_id', 'last_announce_at']);
        $this->assertIndexExists(
            'peers',
            'peers_torrent_seeder_last_announce_idx',
            ['torrent_id', 'is_seeder', 'last_announce_at'],
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertIndexExists(string $table, string $name, array $columns): void
    {
        /** @var list<array{name?: string, columns?: list<string>}> $indexes */
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if (($index['name'] ?? null) === $name && ($index['columns'] ?? []) === $columns) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail(sprintf(
            'Expected index [%s] on [%s] columns [%s].',
            $name,
            $table,
            implode(', ', $columns),
        ));
    }
}
