# NextGN Tracker – Repository Layer

This document summarizes the repository abstractions that ship with the NextGN rebuild. They encapsulate database logic away from controllers/services and keep the Laravel 11 stack testable.

## Purpose
- Decouple the HTTP layer (controllers, Livewire/React bridges) from domain logic and database operations.
- Make domain logic easier to unit test by mocking interfaces instead of hitting the DB.
- Provide a single, coherent place for queries, sorting, filtering, and caching rules shared across the tracker (forums, torrents, messaging).

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

## Dependency injection binding
Bindings live inside `App\Providers\RepositoryServiceProvider`. Ensure the provider is registered in `config/app.php` under `providers`:
```php
'providers' => [
    // ...
    App\Providers\RepositoryServiceProvider::class,
],
```

## Usage in controllers
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
Controllers, jobs, and service classes should always type-hint interfaces to keep swapping to FluentPDO/ExtendedPDO or cache-backed implementations simple.

## CI quality pipeline
`.github/workflows/quality.yml` executes Pest, PHPStan (max level), Rector (dry-run), and Pint style checks. Ship changes only after these workflows pass locally.

## Torrent domain (v1)
- **Database** – The `torrents` table contains `user_id`, `name`, `slug`, `info_hash`, `size`, `files_count`, statistical fields (`seeders`, `leechers`, `completed`), `is_visible`, and timestamps.
- **Model** – `App\Models\Torrent` + `database/factories/TorrentFactory.php` provide realistic seed data and casts.
- **Repository** – `TorrentRepositoryInterface` and `EloquentTorrentRepository` provide pagination for visible torrents, slug lookups, user-created items, and stat increments. Binding is handled in `RepositoryServiceProvider`.
- **HTTP** – `TorrentController` handles `GET /torrents` and `GET /torrents/{slug}` (auth + verified) for list and detail views. Authorization derives from the role ladder (`sysop` → `guest`).

## Tracker engine (v1)
- **Peers** – The `peers` table associates users with torrents, storing `peer_id`, IP, port, up/down statistics, and `last_announce_at`. The model/factory is located at `App\Models\Peer` and `database/factories/PeerFactory.php`.
- **Bencode** – `App\Services\BencodeService` handles bencoding of responses, including lists and dictionaries.
- **Announce** – `GET /announce` (auth, verified, throttled) accepts standard BitTorrent parameters, updates `peers` and torrent statistics (seeders/leechers/completed), and returns a valid bencoded payload. Requests are validated using passkeys plus optional HMAC tokens described in `docs/SECURITY-OVERVIEW.md`.
