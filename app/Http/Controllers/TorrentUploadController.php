<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Http\Requests\Web\TorrentUploadStoreRequest;
use App\Models\Category;
use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Services\Logging\AuditLogger;
use App\Services\Security\SanitizationService;
use App\Services\Torrents\NfoParser;
use App\Services\Torrents\TorrentIngestService;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use App\Services\Torrents\UploadPreflightContextBuilder;
use App\Services\Uploads\NfoStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class TorrentUploadController extends Controller
{
    public function __construct(
        private readonly SanitizationService $sanitizer,
        private readonly TorrentIngestService $ingestService,
        private readonly NfoParser $nfoParser,
        private readonly NfoStorageService $nfoStorage,
        private readonly AuditLogger $auditLogger,
        private readonly UploadEligibilityService $uploadEligibility,
        private readonly UploadPreflightContextBuilder $preflightContextBuilder,
    ) {}

    public function create(): View
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $context = $this->preflightContextBuilder->forUser($user);
        $decision = $this->uploadEligibility->evaluate($user, $context);
        abort_unless($decision->allowed, 403);

        $categories = Category::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('torrents.upload', [
            'categories' => $categories,
        ]);
    }

    public function store(TorrentUploadStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        /** @var \App\Models\User $user */
        $user = $request->user();
        $torrentFile = $request->file('torrent_file');

        if (($torrentFile instanceof UploadedFile) === false) {
            throw ValidationException::withMessages([
                'torrent_file' => 'A valid .torrent file is required.',
            ]);
        }

        $context = $this->preflightContextBuilder->forPayload($user, strval($torrentFile->get()), [
            'type' => $data['type'] ?? null,
            'resolution' => $data['resolution'] ?? null,
        ]);

        $eligibilityDecision = $this->uploadEligibility->evaluate($user, $context);

        if ($eligibilityDecision->allowed === false) {
            return $this->handleDeniedUploadDecision($eligibilityDecision->reason, $eligibilityDecision->context);
        }

        $nfoPayload = $this->resolveNfoText($request, $data);

        $nfoResult = $nfoPayload !== null
            ? $this->nfoParser->parse($nfoPayload)
            : ['sanitized_text' => null, 'imdb_id' => null, 'tmdb_id' => null];

        $torrentSizeBytes = $torrentFile->getSize();
        $torrentMime = $torrentFile->getClientMimeType();

        try {
            $nfoStoragePath = $this->nfoStorage->store($nfoResult['sanitized_text']);
        } catch (RuntimeException) {
            SecurityAuditLog::log($user, 'torrent.upload.rejected', [
                'reason' => 'nfo_storage_failed',
            ]);

            throw ValidationException::withMessages([
                'nfo_file' => 'Unable to store the provided NFO payload at this time.',
            ]);
        }

        try {
            $torrent = $this->ingestService->ingest($user, $torrentFile, [
                'name' => $this->sanitizer->sanitizeString($data['name']),
                'category_id' => $data['category_id'] ?? null,
                'type' => $data['type'],
                'description' => $this->sanitizeNullable($data['description'] ?? null),
                'tags' => $this->normalizeTags($data['tags'] ?? null),
                'source' => $this->sanitizeNullable($data['source'] ?? null),
                'resolution' => $this->sanitizeNullable($data['resolution'] ?? null),
                'codecs' => $this->sanitizeCodecs($data['codecs'] ?? null),
                'nfo_text' => $nfoResult['sanitized_text'],
                'nfo_storage_path' => $nfoStoragePath,
                'imdb_id' => $nfoResult['imdb_id'],
                'tmdb_id' => $nfoResult['tmdb_id'],
                'status' => Torrent::STATUS_PENDING,
            ]);
        } catch (TorrentAlreadyExistsException $exception) {
            SecurityAuditLog::log($user, 'torrent.upload.duplicate', [
                'existing_torrent_id' => $exception->torrent->getKey(),
                'info_hash' => $exception->torrent->info_hash,
            ]);

            return $this->redirectToExistingTorrent($exception->torrent);
        } catch (InvalidArgumentException $exception) {
            SecurityAuditLog::log($user, 'torrent.upload.rejected', [
                'reason' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'torrent_file' => $exception->getMessage(),
            ]);
        }

        $this->auditLogger->log('torrent.created', $torrent, [
            'uploader_id' => $user->getKey(),
            'info_hash' => $torrent->info_hash ?? null,
        ]);

        SecurityAuditLog::log($user, 'torrent.upload', [
            'torrent_id' => $torrent->getKey(),
            'size_bytes' => $torrentSizeBytes,
            'mime' => $torrentMime,
            'nfo_present' => $nfoStoragePath !== null,
            'nfo_bytes' => $this->resolveNfoSize($request, $nfoPayload),
        ]);

        return redirect()
            ->route('torrents.show', $torrent->slug)
            ->with('status', 'Torrent uploaded and awaiting approval.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function handleDeniedUploadDecision(?UploadEligibilityReason $reason, array $context): RedirectResponse
    {
        if ($reason === UploadEligibilityReason::DuplicateTorrent) {
            $existingTorrentId = $context['existing_torrent_id'] ?? null;

            if (is_int($existingTorrentId)) {
                $existingTorrent = Torrent::query()->find($existingTorrentId);

                if ($existingTorrent instanceof Torrent) {
                    return $this->redirectToExistingTorrent($existingTorrent);
                }
            }
        }

        if ($reason === UploadEligibilityReason::MissingMetadata) {
            throw ValidationException::withMessages([
                'torrent_file' => 'Invalid torrent payload: missing required metadata.',
            ]);
        }

        abort(403);
    }

    private function redirectToExistingTorrent(Torrent $torrent): RedirectResponse
    {
        return redirect()
            ->route('torrents.show', $torrent->slug)
            ->with('status', 'Torrent already exists – redirected to the existing entry.');
    }

    private function resolveNfoText(TorrentUploadStoreRequest $request, array $data): ?string
    {
        $file = $request->file('nfo_file');

        if ($file instanceof UploadedFile) {
            return strval($file->get());
        }

        $text = $data['nfo_text'] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    private function resolveNfoSize(TorrentUploadStoreRequest $request, ?string $payload): ?int
    {
        $file = $request->file('nfo_file');

        if ($file instanceof UploadedFile) {
            return $file->getSize();
        }

        if ($payload === null) {
            return null;
        }

        return strlen($payload);
    }

    private function sanitizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = $this->sanitizer->sanitizeString($value);

        return $clean === '' ? null : $clean;
    }

    /**
     * @param  array<int, string>|null  $tags
     * @return array<int, string>|null
     */
    private function normalizeTags(?array $tags): ?array
    {
        if ($tags === null) {
            return null;
        }

        $collection = Collection::make($tags)
            ->filter(fn (string $tag): bool => trim($tag) !== '')
            ->map(fn (string $tag): string => $this->sanitizer->sanitizeString($tag))
            ->filter(fn (string $tag): bool => $tag !== '')
            ->unique()
            ->values();

        return $collection->isEmpty() ? null : $collection->all();
    }

    /**
     * @param  array<string, string|null>|null  $codecs
     * @return array<string, string>|null
     */
    private function sanitizeCodecs(?array $codecs): ?array
    {
        if ($codecs === null) {
            return null;
        }

        $clean = [];

        foreach ($codecs as $key => $value) {
            if (is_string($key) === false || is_string($value) === false) {
                continue;
            }

            $value = $this->sanitizer->sanitizeString($value);

            if ($value === '') {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean === [] ? null : $clean;
    }
}
