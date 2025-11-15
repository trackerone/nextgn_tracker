# NextGN Tracker – Repository Layer

This document describes the repository layer added as part of Sprint 1–2.

## Purpose

- Decouple the HTTP layer (controllers) from domain logic and database operations.
- Make domain logic easier to test in isolation.
- Provide a single, coherent place for queries, sorting, and filtering.

## Contracts

Interfaces are located in `App\Contracts`:

- `UserRepositoryInterface`
- `TopicRepositoryInterface`
- `PostRepositoryInterface`
- `ConversationRepositoryInterface`
- `MessageRepositoryInterface`
- `RoleRepositoryInterface`
- `TorrentRepositoryInterface`

## Implementations

Eloquent-based repositories are located in `App\Repositories`:

- `EloquentUserRepository`
- `EloquentTopicRepository`
- `EloquentPostRepository`
- `EloquentConversationRepository`
- `EloquentMessageRepository`
- `EloquentRoleRepository`
- `EloquentTorrentRepository`

## Dependency Injection Binding

Bindings are defined in `App\Providers\RepositoryServiceProvider`.

Register the provider in `config/app.php` under `providers`:

```php
'providers' => [
    // ...
    App\Providers\RepositoryServiceProvider::class,
],
```

## Usage in Controllers

Example:

```php
use App\Contracts\TopicRepositoryInterface;

class TopicController extends Controller
{
    public function __construct(
        private readonly TopicRepositoryInterface $topics,
    ) {}

    public function index()
    {
        $topics = $this->topics->paginate();

        return view('topics.index', compact('topics'));
    }
}
```

## CI Quality Pipeline

The `.github/workflows/quality.yml` workflow runs:

- Pest tests
- PHPStan analysis
- Pint (code style, dry-run)
- Rector (dry-run)

## Torrent Domain (v1)

- **Database** – The `torrents` table contains `user_id`, `name`, `slug`, `info_hash`, `size`, `files_count`, statistical fields (`seeders`, `leechers`, `completed`), `is_visible`, and timestamps.
- **Model** – `App\Models\Torrent` + `database/factories/TorrentFactory.php` provide realistic seed data and casts.
- **Repository** – `TorrentRepositoryInterface` and `EloquentTorrentRepository` provide pagination for visible torrents, slug lookups, user-created items, and stat increments. Binding is handled in `RepositoryServiceProvider`.
- **HTTP** – `TorrentController` handles `GET /torrents` and `GET /torrents/{slug}` (auth + verified) for list and detail views.

## Tracker Engine (v1)

- **Peers** – The `peers` table associates users with torrents, storing `peer_id`, IP, port, up/down statistics, and `last_announce_at`. The model/factory is located at `App\Models\Peer` and `database/factories/PeerFactory.php`.
- **Bencode** – `App\Services\BencodeService` handles bencoding of responses, including lists and dictionaries.
- **Announce** – `GET /announce` (auth, verified, throttled) accepts standard BitTorrent parameters, updates `peers` and torrent statistics (seeders/leechers/completed), and returns a valid bencoded payload.
