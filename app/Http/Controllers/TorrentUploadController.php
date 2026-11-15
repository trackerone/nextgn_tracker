<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Torrents\SubmitTorrentUploadAction;
use App\Actions\Torrents\SubmitTorrentUploadResult;
use App\Http\Requests\Web\AnalyseTorrentUploadRequest;
use App\Http\Requests\Web\TorrentUploadStoreRequest;
use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Category;
use App\Models\Torrent;
use App\Services\BencodeService;
use App\Services\Torrents\CanonicalTorrentMetadata;
use App\Services\Torrents\TorrentMetadataExtractor;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use App\Services\Torrents\UploadPreflightContextBuilderContract;
use App\Support\Torrents\TorrentPayloadFiles;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TorrentUploadController extends Controller
{
    public function __construct(
        private readonly SubmitTorrentUploadAction $submitTorrentUpload,
        private readonly UploadEligibilityService $uploadEligibility,
        private readonly UploadPreflightContextBuilderContract $preflightContextBuilder,
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
            'releaseAdvice' => is_array($context->releaseAdvice) ? $context->releaseAdvice : null,
        ]);
    }

    public function analyse(
        AnalyseTorrentUploadRequest $request,
        TorrentMetadataExtractor $extractor,
        BencodeService $bencode,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();
        abort_unless($this->uploadEligibility->canUpload($this->preflightContextBuilder->forUser($user)), 403);

        $file = $request->file('torrent_file');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['torrent_file' => 'Choose a valid .torrent file.']);
        }

        $nfoFile = $request->file('nfo_file');
        $rawNfo = $nfoFile instanceof UploadedFile ? strval($nfoFile->get()) : $request->input('nfo_text');

        try {
            $payload = strval($file->get());
            $decoded = $bencode->decode($payload);
            if (! is_array($decoded) || ! is_array($decoded['info'] ?? null)) {
                throw new InvalidArgumentException('Missing torrent info dictionary.');
            }
            $stats = TorrentPayloadFiles::stats($decoded['info']);
            $extracted = $extractor->extract($payload, is_string($rawNfo) ? $rawNfo : null);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['torrent_file' => 'The torrent file could not be read.']);
        }

        $metadata = CanonicalTorrentMetadata::fromExtractedMetadata($extracted)->toPersistenceArray();
        $fields = array_intersect_key($metadata, array_flip([
            'title', 'year', 'resolution', 'source', 'release_group', 'imdb_id', 'tmdb_id', 'audio_language', 'subtitles',
        ]));
        $fields['name'] = $extracted->rawName;
        $names = $extracted->rawName ?? '';
        foreach ($decoded['info']['files'] ?? [] as $entry) {
            $names .= ' '.implode('/', $entry['path'] ?? []);
        }
        $detectedType = null;
        if (preg_match('/\bS\d{1,2}(?:E\d{1,3})?\b/i', $names) === 1) {
            $detectedType = 'tv';
        } elseif (preg_match('/\.(mkv|mp4|avi|mov)\b/i', $names) === 1) {
            $detectedType = 'movie';
        } elseif (preg_match('/\.(mp3|flac|m4a|ogg)\b/i', $names) === 1) {
            $detectedType = 'music';
        }
        $fields['type'] = $detectedType;
        $categorySlug = ['movie' => 'movies', 'tv' => 'tv', 'music' => 'music'][$detectedType ?? ''] ?? null;
        $fields['category_id'] = $categorySlug !== null ? Category::query()->where('slug', $categorySlug)->value('id') : null;
        $fields['codecs[video]'] = $extracted->videoCodec;
        $fields['codecs[audio]'] = $extracted->audioCodec;
        $warnings = [];
        if (
            preg_match_all('/\btt\d{7,8}\b/i', $extracted->rawNfo ?? '', $matches) > 1
            && count(array_unique($matches[0])) > 1
        ) {
            $warnings[] = 'Multiple films detected: review the collection title and year. No single IMDb identity was selected.';
        }

        return response()->json([
            'fields' => array_filter($fields, static fn (mixed $value): bool => $value !== null && $value !== ''),
            'files' => $stats['count'],
            'warnings' => $warnings,
        ]);
    }

    public function store(TorrentUploadStoreRequest $request): RedirectResponse
    {
        $torrentFile = $request->file('torrent_file');

        if (($torrentFile instanceof UploadedFile) === false) {
            throw ValidationException::withMessages([
                'torrent_file' => 'Choose a valid .torrent file before submitting.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $nfoFile = $request->file('nfo_file');
        $result = $this->submitTorrentUpload->execute(
            $user,
            $torrentFile,
            $request->validated(),
            $nfoFile instanceof UploadedFile ? $nfoFile : null,
        );

        if ($result->isDuplicate()) {
            if ($result->duplicateTorrent instanceof Torrent) {
                return $this->redirectToExistingTorrent($result->duplicateTorrent);
            }

            throw ValidationException::withMessages([
                'torrent_file' => 'Exact duplicate: the info hash matches an existing upload.',
            ]);
        }

        if ($result->isDenied()) {
            return $this->handleDeniedUploadResult($result);
        }

        if (! $result->torrent instanceof Torrent) {
            abort(403);
        }

        return $this->successfulUploadResponse($result->torrent);
    }

    private function handleDeniedUploadResult(SubmitTorrentUploadResult $result): RedirectResponse
    {
        $decision = $result->deniedDecision;

        if ($decision?->reason === UploadEligibilityReason::MissingMetadata) {
            throw ValidationException::withMessages([
                'torrent_file' => 'Missing release metadata: add a clearer name, type, source or resolution.',
            ]);
        }

        abort(403);
    }

    private function redirectToExistingTorrent(Torrent $torrent): RedirectResponse
    {
        return redirect()
            ->route('torrents.show', $torrent->slug)
            ->with('status', 'Exact duplicate found; you were redirected to the existing torrent entry.');
    }

    private function successfulUploadResponse(Torrent $torrent): RedirectResponse
    {
        $uploadMetadata = TorrentMetadataView::forTorrent(
            $torrent->loadMissing('metadata')
        );

        return redirect()
            ->route('torrents.show', $torrent->slug)
            ->with('status', 'Torrent submitted for moderation and hidden until any required approval is complete.')
            ->with('upload_metadata', $uploadMetadata);
    }
}
