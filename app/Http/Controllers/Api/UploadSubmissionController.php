<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTorrentRequest;
use App\Http\Resources\UploadSubmissionResource;
use App\Models\User;
use App\Services\Security\SanitizationService;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use App\Services\Torrents\TorrentIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class UploadSubmissionController extends Controller
{
    public function __construct(
        private readonly TorrentIngestService $ingestService,
        private readonly SanitizationService $sanitizer,
        private readonly UploadEligibilityService $uploadEligibility,
    ) {
    }

    public function store(StoreTorrentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $torrentFile = $request->file('torrent_file');

        if (! ($torrentFile instanceof UploadedFile)) {
            throw ValidationException::withMessages([
                'torrent_file' => 'A valid .torrent file is required.',
            ]);
        }

        $data = $request->validated();

        $decision = $this->uploadEligibility->evaluateForPayload($user, (string)$torrentFile->get(), [
            'type' => $data['type'] ?? null,
            'resolution' => $data['resolution'] ?? null,
        ]);

        if (! $decision->allowed) {
            if ($decision->reason === UploadEligibilityReason::DuplicateTorrent) {
                return response()->json(['message' => 'Torrent already exists.'], 409);
            }

            if ($decision->reason === UploadEligibilityReason::MissingMetadata) {
                throw ValidationException::withMessages([
                    'torrent_file' => 'Invalid torrent payload: missing required metadata.',
                ]);
            }

            abort(403);
        }

        try {
            $torrent = $this->ingestService->ingest($user, $torrentFile, [
                'name' => $this->sanitizer->sanitizeString((string)$data['name']),
                'category_id' => $data['category_id'] ?? null,
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'tags' => $data['tags'] ?? null,
                'source' => $data['source'] ?? null,
                'resolution' => $data['resolution'] ?? null,
                'codecs' => $data['codecs'] ?? null,
                'imdb_id' => $data['imdb_id'] ?? null,
                'tmdb_id' => $data['tmdb_id'] ?? null,
            ]);
        } catch (TorrentAlreadyExistsException) {
            return response()->json(['message' => 'Torrent already exists.'], 409);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'torrent_file' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'data' => (new UploadSubmissionResource($torrent))->resolve(),
        ], 201);
    }
}
