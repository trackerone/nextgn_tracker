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

## Implementeringer

Eloquent-baserede repositories findes i `App\Repositories`:

- `EloquentUserRepository`
- `EloquentTopicRepository`
- `EloquentPostRepository`
- `EloquentConversationRepository`
- `EloquentMessageRepository`
- `EloquentRoleRepository`

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
