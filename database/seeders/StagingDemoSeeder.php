<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Peer;
use App\Models\Post;
use App\Models\Role;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\TorrentExternalMetadata;
use App\Models\TorrentFollow;
use App\Models\TorrentMetadata;
use App\Models\TorrentUserStat;
use App\Models\User;
use App\Models\UserTorrent;
use App\Services\MarkdownService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class StagingDemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->guardProduction();

        DB::transaction(function (): void {
            $this->call([RoleSeeder::class, CategorySeeder::class]);

            $users = $this->seedUsers();
            $this->seedTorrents($users);
            $this->seedForum($users);
            $this->seedPrivateMessages($users);
            $this->seedFollows($users);
        });
    }

    private function guardProduction(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $force = (bool) ($this->command?->option('force') ?? false);

        if (! $force) {
            throw new RuntimeException('StagingDemoSeeder is disabled in production. Re-run with php artisan db:seed --class=StagingDemoSeeder --force only for an explicitly approved non-production clone.');
        }
    }

    /** @return array<string, User> */
    private function seedUsers(): array
    {
        $definitions = [
            ['key' => 'sysop', 'name' => 'Mira Sysop', 'email' => 'mira.sysop@example.test', 'role' => User::ROLE_SYSOP, 'role_slug' => 'sysop'],
            ['key' => 'mod', 'name' => 'Noah Moderator', 'email' => 'noah.mod@example.test', 'role' => User::ROLE_MODERATOR, 'role_slug' => 'mod1'],
            ['key' => 'uploader', 'name' => 'Iris Uploader', 'email' => 'iris.uploads@example.test', 'role' => User::ROLE_UPLOADER, 'role_slug' => 'uploader3'],
            ['key' => 'archivist', 'name' => 'Theo Archivist', 'email' => 'theo.archive@example.test', 'role' => User::ROLE_POWER_USER, 'role_slug' => 'uploader2'],
            ['key' => 'member', 'name' => 'Sam Member', 'email' => 'sam.member@example.test', 'role' => User::ROLE_USER, 'role_slug' => 'user2'],
            ['key' => 'newbie', 'name' => 'Jules Newbie', 'email' => 'jules.newbie@example.test', 'role' => User::ROLE_USER, 'role_slug' => 'newbie'],
        ];

        $users = [];

        foreach ($definitions as $definition) {
            $role = Role::query()->where('slug', $definition['role_slug'])->first();
            $user = User::query()->firstOrNew(['email' => $definition['email']]);
            $user->forceFill([
                'name' => $definition['name'],
                'email_verified_at' => Carbon::parse('2026-04-01 12:00:00'),
                'password' => Hash::make(self::PASSWORD),
                'role_id' => $role?->id,
                'role' => $definition['role'],
                'is_staff' => $role !== null && (bool) $role->is_staff,
                'is_banned' => false,
                'is_disabled' => false,
                'passkey' => substr(hash('sha256', 'nextgn-staging-'.$definition['email']), 0, 32),
            ])->save();

            $users[$definition['key']] = $user;
        }

        return $users;
    }

    /** @param array<string, User> $users @return array<string, Torrent> */
    private function seedTorrents(array $users): array
    {
        $now = Carbon::parse('2026-05-17 10:00:00');
        $rows = [
            ['key' => 'nebula-2160', 'title' => 'The Last Nebula', 'year' => 2026, 'type' => 'movie', 'category' => 'Movies', 'resolution' => '2160p', 'source' => 'bluray', 'group' => 'NXT', 'size' => 68_450_000_000, 'files' => 7, 'seeders' => 42, 'leechers' => 8, 'completed' => 214, 'age' => 2, 'freeleech' => true, 'internal' => true, 'tmdb' => 910001, 'imdb' => 'tt9100011', 'uploader' => 'uploader', 'status' => Torrent::STATUS_PUBLISHED],
            ['key' => 'nebula-1080', 'title' => 'The Last Nebula', 'year' => 2026, 'type' => 'movie', 'category' => 'Movies', 'resolution' => '1080p', 'source' => 'web', 'group' => 'FLUX', 'size' => 14_800_000_000, 'files' => 3, 'seeders' => 25, 'leechers' => 5, 'completed' => 187, 'age' => 5, 'freeleech' => false, 'internal' => false, 'tmdb' => 910001, 'imdb' => 'tt9100011', 'uploader' => 'uploader', 'status' => Torrent::STATUS_PUBLISHED],
            ['key' => 'harbor-s02e04', 'title' => 'Harbor Lights S02E04', 'year' => 2026, 'type' => 'tv', 'category' => 'TV', 'resolution' => '1080p', 'source' => 'web', 'group' => 'NTB', 'size' => 4_250_000_000, 'files' => 2, 'seeders' => 18, 'leechers' => 12, 'completed' => 93, 'age' => 1, 'freeleech' => false, 'internal' => false, 'tmdb' => 920204, 'imdb' => 'tt9202040', 'uploader' => 'uploader', 'status' => Torrent::STATUS_PUBLISHED],
            ['key' => 'harbor-s02e04-720', 'title' => 'Harbor Lights S02E04', 'year' => 2026, 'type' => 'tv', 'category' => 'TV', 'resolution' => '720p', 'source' => 'hdtv', 'group' => 'Kitsune', 'size' => 1_530_000_000, 'files' => 1, 'seeders' => 7, 'leechers' => 2, 'completed' => 71, 'age' => 3, 'freeleech' => false, 'internal' => false, 'tmdb' => 920204, 'imdb' => 'tt9202040', 'uploader' => 'archivist', 'status' => Torrent::STATUS_PUBLISHED],
            ['key' => 'atlas-ost', 'title' => 'Atlas Run Original Soundtrack', 'year' => 2024, 'type' => 'music', 'category' => 'Music', 'resolution' => null, 'source' => 'web', 'group' => 'LOG', 'size' => 912_000_000, 'files' => 24, 'seeders' => 6, 'leechers' => 0, 'completed' => 55, 'age' => 90, 'freeleech' => true, 'internal' => true, 'tmdb' => 940441, 'imdb' => null, 'uploader' => 'archivist', 'status' => Torrent::STATUS_PUBLISHED],
            ['key' => 'retro-archive', 'title' => 'Silent Arcade Collection', 'year' => 1998, 'type' => 'game', 'category' => 'Games', 'resolution' => null, 'source' => 'archive', 'group' => 'Vault', 'size' => 2_700_000_000, 'files' => 42, 'seeders' => 1, 'leechers' => 0, 'completed' => 19, 'age' => 950, 'freeleech' => false, 'internal' => false, 'tmdb' => null, 'imdb' => null, 'uploader' => 'archivist', 'status' => Torrent::STATUS_PUBLISHED],
            ['key' => 'dead-doc', 'title' => 'Deep Ice Expedition', 'year' => 2012, 'type' => 'movie', 'category' => 'Movies', 'resolution' => '720p', 'source' => 'dvd', 'group' => 'OldTown', 'size' => 3_850_000_000, 'files' => 5, 'seeders' => 0, 'leechers' => 0, 'completed' => 8, 'age' => 2200, 'freeleech' => false, 'internal' => false, 'tmdb' => 812012, 'imdb' => 'tt8120120', 'uploader' => 'archivist', 'status' => Torrent::STATUS_PUBLISHED],
            ['key' => 'pending-encode', 'title' => 'Signal Kitchen S01E01', 'year' => 2026, 'type' => 'tv', 'category' => 'TV', 'resolution' => '2160p', 'source' => 'web', 'group' => 'NXT', 'size' => 8_620_000_000, 'files' => 2, 'seeders' => 0, 'leechers' => 0, 'completed' => 0, 'age' => 0, 'freeleech' => false, 'internal' => true, 'tmdb' => 930101, 'imdb' => 'tt9301010', 'uploader' => 'uploader', 'status' => Torrent::STATUS_PENDING],
            ['key' => 'rejected-cam', 'title' => 'City of Glass', 'year' => 2026, 'type' => 'movie', 'category' => 'Movies', 'resolution' => '720p', 'source' => 'cam', 'group' => 'CAMERA', 'size' => 2_200_000_000, 'files' => 1, 'seeders' => 0, 'leechers' => 0, 'completed' => 0, 'age' => 6, 'freeleech' => false, 'internal' => false, 'tmdb' => 910009, 'imdb' => 'tt9100099', 'uploader' => 'member', 'status' => Torrent::STATUS_REJECTED],
        ];

        $torrents = [];

        foreach ($rows as $row) {
            $torrent = $this->upsertTorrent($row, $users, $now);
            $this->upsertMetadata($torrent, $row);
            $this->seedSwarm($torrent, $row, array_values($users), $now);
            $torrents[$row['key']] = $torrent;
        }

        return $torrents;
    }

    /** @param array<string, mixed> $row @param array<string, User> $users */
    private function upsertTorrent(array $row, array $users, Carbon $now): Torrent
    {
        $category = Category::query()->where('name', $row['category'])->firstOrFail();
        $uploadedAt = $now->copy()->subDays((int) $row['age']);
        $published = $row['status'] === Torrent::STATUS_PUBLISHED;
        $moderator = $users['mod'];
        $name = $this->releaseName($row);

        return Torrent::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'user_id' => $users[$row['uploader']]->id,
                'category_id' => $category->id,
                'name' => $name,
                'info_hash' => strtoupper(substr(hash('sha1', 'nextgn-demo-'.$row['key']), 0, 40)),
                'storage_path' => 'staging-demo/'.Str::slug((string) $row['key']).'.torrent',
                'size_bytes' => $row['size'],
                'file_count' => $row['files'],
                'files_count' => $row['files'],
                'type' => $row['type'],
                'source' => $row['source'],
                'resolution' => $row['resolution'],
                'codecs' => ['video' => $row['resolution'] === null ? null : 'x265', 'audio' => $row['type'] === 'music' ? 'FLAC' : 'DTS-HD MA'],
                'tags' => array_values(array_filter([$row['internal'] ? 'internal' : null, $row['freeleech'] ? 'freeleech' : null, $row['age'] > 365 ? 'archive' : null])),
                'description' => 'Staging demo upload with deterministic tracker, metadata and moderation state.',
                'nfo_text' => sprintf("%s\nGroup: %s\nSource: %s", $name, $row['group'], $row['source'] ?? 'mixed'),
                'imdb_id' => $row['imdb'],
                'tmdb_id' => $row['tmdb'],
                'seeders' => $row['seeders'],
                'leechers' => $row['leechers'],
                'completed' => $row['completed'],
                'is_visible' => $published,
                'is_approved' => $published,
                'is_banned' => $row['status'] === Torrent::STATUS_REJECTED,
                'ban_reason' => $row['status'] === Torrent::STATUS_REJECTED ? 'Rejected in staging demo: poor source quality.' : null,
                'freeleech' => $row['freeleech'],
                'is_freeleech' => $row['freeleech'],
                'status' => $row['status'],
                'published_at' => $published ? $uploadedAt : null,
                'moderated_by' => $row['status'] === Torrent::STATUS_PENDING ? null : $moderator->id,
                'moderated_at' => $row['status'] === Torrent::STATUS_PENDING ? null : $uploadedAt->copy()->addMinutes(18),
                'moderated_reason' => $row['status'] === Torrent::STATUS_REJECTED ? 'CAM/low quality source is not accepted.' : 'Approved for staging demo catalogue.',
                'original_filename' => $name.'.torrent',
                'uploaded_at' => $uploadedAt,
                'created_at' => $uploadedAt,
                'updated_at' => $uploadedAt,
            ]
        );
    }

    /** @param array<string, mixed> $row */
    private function upsertMetadata(Torrent $torrent, array $row): void
    {
        TorrentMetadata::query()->updateOrCreate(
            ['torrent_id' => $torrent->id],
            [
                'title' => $row['title'],
                'year' => $row['year'],
                'type' => $row['type'],
                'resolution' => $row['resolution'],
                'source' => $row['source'],
                'release_group' => $row['group'],
                'imdb_id' => $row['imdb'],
                'imdb_url' => $row['imdb'] === null ? null : 'https://www.imdb.com/title/'.$row['imdb'].'/',
                'tmdb_id' => $row['tmdb'],
                'tmdb_url' => $row['tmdb'] === null ? null : 'https://www.themoviedb.org/title/'.$row['tmdb'],
                'nfo' => 'Deterministic staging metadata generated for realistic browse and detail pages.',
                'raw_name' => $torrent->name,
                'parsed_name' => $row['title'],
                'raw_payload' => ['demo_seed' => true, 'release_group' => $row['group']],
            ]
        );

        TorrentExternalMetadata::query()->updateOrCreate(
            ['torrent_id' => $torrent->id],
            [
                'imdb_id' => $row['imdb'],
                'tmdb_id' => $row['tmdb'] === null ? null : (string) $row['tmdb'],
                'title' => $row['title'],
                'original_title' => $row['title'],
                'year' => $row['year'],
                'media_type' => in_array($row['type'], ['movie', 'tv'], true) ? $row['type'] : 'movie',
                'overview' => 'Believable staging-only external metadata for catalogue evaluation.',
                'providers_payload' => ['staging' => ['id' => $row['key']]],
                'enriched_at' => Carbon::parse('2026-05-17 10:00:00'),
                'enrichment_status' => 'enriched',
                'last_error' => null,
            ]
        );
    }

    /** @param array<string, mixed> $row @param list<User> $users */
    private function seedSwarm(Torrent $torrent, array $row, array $users, Carbon $now): void
    {
        Peer::query()->where('torrent_id', $torrent->id)->delete();
        UserTorrent::query()->where('torrent_id', $torrent->id)->delete();
        TorrentUserStat::query()->where('torrent_id', $torrent->id)->delete();

        $activePeers = (int) $row['seeders'] + (int) $row['leechers'];

        for ($i = 0; $i < $activePeers; $i++) {
            $isSeeder = $i < (int) $row['seeders'];
            $user = $users[$i % count($users)];
            Peer::query()->create([
                'torrent_id' => $torrent->id,
                'user_id' => $user->id,
                'peer_id' => substr(hash('sha1', $torrent->id.'-'.$user->id.'-'.$i), 0, 20),
                'ip' => '10.42.'.($torrent->id % 200).'.'.($i + 10),
                'port' => 51000 + $i,
                'uploaded' => $isSeeder ? (int) $row['size'] * max(1, (int) floor(((int) $row['completed']) / 10)) : 0,
                'downloaded' => $isSeeder ? (int) $row['size'] : (int) floor(((int) $row['size']) * 0.35),
                'left' => $isSeeder ? 0 : (int) floor(((int) $row['size']) * 0.65),
                'is_seeder' => $isSeeder,
                'client' => $i % 2 === 0 ? 'qBittorrent/5.0.4' : 'Transmission/4.0.6',
                'is_banned_client' => false,
                'spoof_score' => 0,
                'last_action' => $isSeeder ? 'started' : 'downloading',
                'last_announce_at' => $now->copy()->subMinutes(5 + $i),
            ]);
        }

        foreach (array_slice($users, 0, min(count($users), (int) $row['completed'] > 0 ? 4 : 0)) as $index => $user) {
            UserTorrent::query()->create([
                'user_id' => $user->id,
                'torrent_id' => $torrent->id,
                'uploaded' => (int) $row['size'] * (2 + $index),
                'downloaded' => (int) $row['size'],
                'completed_at' => $now->copy()->subDays((int) $row['age'])->addHours($index + 1),
                'last_announce_at' => $now->copy()->subMinutes(15 + $index),
                'first_grab_at' => $now->copy()->subDays((int) $row['age'])->addMinutes(20),
                'last_grab_at' => $now->copy()->subDays((int) $row['age'])->addHours($index + 1),
            ]);

            TorrentUserStat::query()->create([
                'user_id' => $user->id,
                'torrent_id' => $torrent->id,
                'uploaded_bytes' => (int) $row['size'] * (2 + $index),
                'downloaded_bytes' => (int) $row['size'],
                'seed_time_seconds' => 86_400 * (1 + $index),
                'leech_time_seconds' => 3_600 * (1 + $index),
                'times_completed' => 1,
                'first_completed_at' => $now->copy()->subDays((int) $row['age'])->addHours($index + 1),
                'last_completed_at' => $now->copy()->subDays((int) $row['age'])->addHours($index + 1),
                'last_announced_at' => $now->copy()->subMinutes(15 + $index),
            ]);
        }
    }

    /** @param array<string, User> $users */
    private function seedForum(array $users): void
    {
        $topics = [
            ['slug' => 'welcome-to-the-staging-tracker', 'title' => 'Welcome to the staging tracker', 'user' => 'sysop', 'pinned' => true, 'locked' => false, 'posts' => ['Use this environment for screenshots and review only.', 'Fresh uploads and old archives are both represented in browse.']],
            ['slug' => 'upload-offers-may-2026', 'title' => 'Upload offers: May 2026', 'user' => 'uploader', 'pinned' => false, 'locked' => false, 'posts' => ['I can cover more WEB-DL TV packs this week.', 'Please prioritize well-seeded internal encodes.']],
            ['slug' => 'moderation-notes-source-quality', 'title' => 'Moderation notes: source quality', 'user' => 'mod', 'pinned' => false, 'locked' => true, 'posts' => ['Reminder: CAM sources are rejected for the staging catalogue.']],
        ];

        foreach ($topics as $topicRow) {
            $topic = Topic::query()->updateOrCreate(
                ['slug' => $topicRow['slug']],
                ['title' => $topicRow['title'], 'user_id' => $users[$topicRow['user']]->id, 'is_pinned' => $topicRow['pinned'], 'is_locked' => $topicRow['locked']]
            );

            Post::query()->where('topic_id', $topic->id)->delete();
            foreach ($topicRow['posts'] as $index => $body) {
                $this->createPost($topic, $users[array_keys($users)[$index % count($users)]], $body, $index);
            }
        }
    }

    private function createPost(Topic $topic, User $user, string $body, int $index): void
    {
        Post::query()->create([
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'body_md' => $body,
            'body_html' => app(MarkdownService::class)->render($body),
            'created_at' => Carbon::parse('2026-05-16 09:00:00')->addMinutes($index * 12),
            'updated_at' => Carbon::parse('2026-05-16 09:00:00')->addMinutes($index * 12),
        ]);
    }

    /** @param array<string, User> $users */
    private function seedPrivateMessages(array $users): void
    {
        $pairs = [['mod', 'uploader'], ['member', 'archivist'], ['newbie', 'sysop']];

        foreach ($pairs as $index => [$a, $b]) {
            $conversation = Conversation::query()->updateOrCreate([
                'user_a_id' => min($users[$a]->id, $users[$b]->id),
                'user_b_id' => max($users[$a]->id, $users[$b]->id),
            ], ['last_message_at' => Carbon::parse('2026-05-16 18:00:00')->addMinutes($index * 20)]);

            Message::query()->where('conversation_id', $conversation->id)->delete();
            foreach (['Can you take a look at this release before I upload?', 'Looks good for staging; please include the NFO.'] as $messageIndex => $body) {
                Message::query()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $messageIndex === 0 ? $users[$a]->id : $users[$b]->id,
                    'body_md' => $body,
                    'body_html' => app(MarkdownService::class)->render($body),
                    'read_at' => $messageIndex === 0 ? Carbon::parse('2026-05-16 18:30:00') : null,
                    'created_at' => Carbon::parse('2026-05-16 18:00:00')->addMinutes($index * 20 + $messageIndex * 6),
                    'updated_at' => Carbon::parse('2026-05-16 18:00:00')->addMinutes($index * 20 + $messageIndex * 6),
                ]);
            }
        }
    }

    /** @param array<string, User> $users */
    private function seedFollows(array $users): void
    {
        $follows = [
            ['user' => 'member', 'title' => 'The Last Nebula', 'type' => 'movie', 'resolution' => '2160p', 'source' => 'bluray', 'year' => 2026],
            ['user' => 'newbie', 'title' => 'Harbor Lights S02E04', 'type' => 'tv', 'resolution' => '1080p', 'source' => 'web', 'year' => 2026],
            ['user' => 'archivist', 'title' => 'Silent Arcade Collection', 'type' => 'game', 'resolution' => null, 'source' => 'archive', 'year' => 1998],
        ];

        foreach ($follows as $follow) {
            TorrentFollow::query()->updateOrCreate(
                ['user_id' => $users[$follow['user']]->id, 'normalized_title' => $this->normalizedTitle($follow['title'])],
                [
                    'title' => $follow['title'],
                    'type' => $follow['type'],
                    'resolution' => $follow['resolution'],
                    'source' => $follow['source'],
                    'year' => $follow['year'],
                    'last_checked_at' => Carbon::parse('2026-05-17 10:00:00'),
                ]
            );
        }
    }

    /** @param array<string, mixed> $row */
    private function releaseName(array $row): string
    {
        return implode('.', array_filter([
            Str::of((string) $row['title'])->replace(' ', '.')->value(),
            $row['year'],
            $row['resolution'],
            Str::upper((string) $row['source']),
            $row['resolution'] === null ? null : 'x265',
            $row['type'] === 'music' ? 'FLAC' : null,
            $row['group'],
        ]));
    }

    private function normalizedTitle(string $title): string
    {
        return Str::of($title)->lower()->replaceMatches('/[^a-z0-9]+/i', ' ')->squish()->value();
    }
}
