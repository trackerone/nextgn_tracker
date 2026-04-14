<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadSubmissionRequest;
use App\Http\Resources\UploadSubmissionResource;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Security\SanitizationService;
use App\Services\Torrents\TorrentIngestService;
use App\Services\Torrents\UploadEligibilityDecision;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use App\Services\Torrents\UploadPreflightContextBuilder;
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
        private readonly UploadPreflightContextBuilder $preflightContextBuilder,
    ) {}

    public function store(UploadSubmissionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $torrentFile = $request->file('torrent_file');

        if (($torrentFile instanceof UploadedFile) === false) {
            throw ValidationException::withMessages([
                'torrent_file' => 'A valid .torrent file is required.',
            ]);
        }

        $data = $request->validated();

        $context = $this->preflightContextBuilder->forPayload($user, strval($torrentFile->get()), [
            'type' => $data['type'] ?? null,
            'resolution' => $data['resolution'] ?? null,
        ]);

        $decision = $this->uploadEligibility->evaluate($user, $context);

        if ($decision->allowed === false) {
            return $this->mapDeniedEligibilityToApiResponse($decision);
        }

        try {
            $torrent = $this->ingestService->ingest($user, $torrentFile, [
                'name' => $this->sanitizer->sanitizeString(strval($data['name'])),
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
            return $this->duplicateConflictResponse();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'torrent_file' => $exception->getMessage(),
            ]);
        }

        return $this->successfulUploadResponse($torrent);
    }

    private function mapDeniedEligibilityToApiResponse(UploadEligibilityDecision $decision): JsonResponse
    {
        if ($decision->reason === UploadEligibilityReason::DuplicateTorrent) {
            return $this->duplicateConflictResponse();
        }

        if ($decision->reason === UploadEligibilityReason::MissingMetadata) {
            throw ValidationException::withMessages([
                'torrent_file' => 'Invalid torrent payload: missing required metadata.',
            ]);
        }

        abort(403);
    }

    private function duplicateConflictResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Torrent already exists.',
            'error' => 'duplicate_torrent',
        ], 409);
    }

    private function successfulUploadResponse(Torrent $torrent): JsonResponse
    {
        return response()->json([
            'data' => (new UploadSubmissionResource($torrent))->resolve(),
        ], 201);
    }
}
