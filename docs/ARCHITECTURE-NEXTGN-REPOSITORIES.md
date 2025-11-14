# NextGN Tracker – Repository-lag

Dette dokument beskriver det repository-lag, der er tilføjet som del af Sprint 1–2.

## Formål

- Adskille HTTP-lag (controllers) fra domænelogik og databaserelaterede kald.
- Gøre det nemmere at teste domænelogik isoleret.
- Skabe et entydigt sted at lægge queries, sortering og filtrering.

## Kontrakter

Interfaces findes i `App\Contracts`:

- `UserRepositoryInterface`
- `TopicRepositoryInterface`
- `PostRepositoryInterface`
- `ConversationRepositoryInterface`
- `MessageRepositoryInterface`
- `RoleRepositoryInterface`
- `TorrentRepositoryInterface`

## Implementeringer

Eloquent-baserede repositories findes i `App\Repositories`:

- `EloquentUserRepository`
- `EloquentTopicRepository`
- `EloquentPostRepository`
- `EloquentConversationRepository`
- `EloquentMessageRepository`
- `EloquentRoleRepository`
- `EloquentTorrentRepository`

## DI-binding

Bindings findes i `App\Providers\RepositoryServiceProvider`.

Registrér provider'en i `config/app.php` under `providers`:

```php
'providers' => [
    // ...
    App\Providers\RepositoryServiceProvider::class,
],
```

## Anvendelse i controllers

Eksempel:

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

## CI-kvalitet

Workflow-filen `.github/workflows/quality.yml` kører:

- Pest tests
- PHPStan analyse
- Pint (kode-stil, dry-run)
- Rector (dry-run)

## Torrent domain (v1)

- **Database** – `torrents`-tabellen rummer `user_id`, `name`, `slug`, `info_hash`, `size`, `files_count`, statistikfelter (`seeders`, `leechers`, `completed`) samt `is_visible` og timestamps.
- **Model** – `App\Models\Torrent` + `database/factories/TorrentFactory.php` giver realistiske data og casts.
- **Repository** – `TorrentRepositoryInterface` og `EloquentTorrentRepository` leverer pagination af synlige torrents, slug-opslag, brugeroprettelser og stats-inkrementer. Binding ligger i `RepositoryServiceProvider`.
- **HTTP** – `TorrentController` håndterer `GET /torrents` og `GET /torrents/{slug}` (auth + verificeret) for liste- og detailvisning.

## Tracker engine (v1)

- **Peers** – `peers`-tabellen binder brugere til torrents med `peer_id`, IP, port, up/down-statistik samt `last_announce_at`. Modellen/factory findes i `App\Models\Peer` og `database/factories/PeerFactory.php`.
- **Bencode** – `App\Services\BencodeService` håndterer bencoding af svar, inkl. lister og dictionaries.
- **Announce** – `GET /announce` (auth, verificeret, throttlet) tager standard BitTorrent-parametre, opdaterer `peers` og torrent-statistik (seeders/leechers/completed) og svarer med et gyldigt bencoded payload.
