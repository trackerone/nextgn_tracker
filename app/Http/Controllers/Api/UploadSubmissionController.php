<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Torrents\SubmitTorrentUploadAction;
use App\Actions\Torrents\SubmitTorrentUploadResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadSubmissionRequest;
use App\Http\Resources\UploadSubmissionResource;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\UploadEligibilityReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class UploadSubmissionController extends Controller
{
    public function __construct(
        private readonly SubmitTorrentUploadAction $submitTorrentUpload,
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

        $nfoFile = $request->file('nfo_file');
        $result = $this->submitTorrentUpload->execute(
            $user,
            $torrentFile,
            $request->validated(),
            $nfoFile instanceof UploadedFile ? $nfoFile : null,
        );

        if ($result->isDuplicate()) {
            return $this->duplicateConflictResponse(
                $result->duplicateTorrent,
                $result->releaseAdvice,
                $result->metadataEnrichmentOutcome,
            );
        }

        if ($result->isDenied()) {
            return $this->mapDeniedUploadResultToApiResponse($result);
        }

        if (!$result->torrent instanceof Torrent) {
            abort(403);
        }

        return $this->successfulUploadResponse(
            $result->torrent,
            $result->releaseAdvice,
            $result->metadataEnrichmentOutcome,
        );
    }

    private function mapDeniedUploadResultToApiResponse(SubmitTorrentUploadResult $result): JsonResponse
    {
        if ($result->deniedDecision?->reason === UploadEligibilityReason::MissingMetadata) {
            throw ValidationException::withMessages([
                'torrent_file' => 'Invalid torrent payload: missing required metadata.',
            ]);
        }

        abort(403);
    }

    /**
     * @param  array<string, mixed>|null  $releaseAdvice
     * @param  array{metadata_enrichment_applied_fields: list<string>, metadata_enrichment_conflicts: list<string>}  $metadataEnrichmentOutcome
     */
    private function duplicateConflictResponse(
        ?Torrent $existingTorrent = null,
        ?array $releaseAdvice = null,
        array $metadataEnrichmentOutcome = [
            'metadata_enrichment_applied_fields' => [],
            'metadata_enrichment_conflicts' => [],
        ],
    ): JsonResponse
    {
        $payload = [
            'message' => 'Torrent already exists.',
            'error' => 'duplicate_torrent',
            'duplicate' => true,
        ];

        if ($releaseAdvice !== null) {
            $payload['release_advice'] = $releaseAdvice;
        }

        $payload = array_merge($payload, $metadataEnrichmentOutcome);

        if ($existingTorrent instanceof Torrent) {
            $payload['existing_torrent'] = [
                'id' => $existingTorrent->getKey(),
                'slug' => $existingTorrent->slug,
            ];
        }

        return response()->json($payload, 409);
    }

    /**
     * @param  array<string, mixed>|null  $releaseAdvice
     * @param  array{metadata_enrichment_applied_fields: list<string>, metadata_enrichment_conflicts: list<string>}  $metadataEnrichmentOutcome
     */
    private function successfulUploadResponse(
        Torrent $torrent,
        ?array $releaseAdvice = null,
        array $metadataEnrichmentOutcome = [
            'metadata_enrichment_applied_fields' => [],
            'metadata_enrichment_conflicts' => [],
        ],
    ): JsonResponse
    {
        $payload = [
            'data' => (new UploadSubmissionResource($torrent))->resolve(),
        ];

        if ($releaseAdvice !== null) {
            $payload['release_advice'] = $releaseAdvice;
        }

        $payload = array_merge($payload, $metadataEnrichmentOutcome);

        return response()->json($payload, 201);
    }
}
