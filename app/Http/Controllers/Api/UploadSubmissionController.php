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
use App\Services\Torrents\CanonicalTorrentMetadata;
use App\Services\Torrents\DuplicateTorrentResolver;
use App\Services\Torrents\PersistTorrentMetadataService;
use App\Services\Torrents\TorrentIngestService;
use App\Services\Torrents\UploadEligibilityDecision;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use App\Services\Torrents\UploadPreflightContextBuilderContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class UploadSubmissionController extends Controller
{
    public function __construct(
        private readonly TorrentIngestService $ingestService,
        private readonly SanitizationService $sanitizer,
        private readonly PersistTorrentMetadataService $metadataPersistence,
        private readonly UploadEligibilityService $uploadEligibility,
        private readonly UploadPreflightContextBuilderContract $preflightContextBuilder,
        private readonly DuplicateTorrentResolver $duplicateTorrentResolver,
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
            'nfo_text' => $data['nfo_text'] ?? null,
        ]);

        $decision = $this->uploadEligibility->evaluate($user, $context);

        if ($decision->allowed === false) {
            return $this->mapDeniedEligibilityToApiResponse($decision);
        }

        $metadata = $context->extractedMetadata;

        try {
            $torrent = $this->ingestService->ingest($user, $torrentFile, [
                'name' => $this->sanitizer->sanitizeString(strval($data['name'])),
                'category_id' => $data['category_id'] ?? null,
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'tags' => $data['tags'] ?? null,
                'source' => $data['source'] ?? $metadata->source,
                'resolution' => $data['resolution'] ?? $metadata->resolution,
                'codecs' => $data['codecs'] ?? null,
                'nfo_text' => $metadata->rawNfo,
                'imdb_id' => $data['imdb_id'] ?? $metadata->imdbId,
                'tmdb_id' => $data['tmdb_id'] ?? $metadata->tmdbId,
            ]);
        } catch (TorrentAlreadyExistsException $exception) {
            return $this->duplicateConflictResponse($exception->torrent);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'torrent_file' => $exception->getMessage(),
            ]);
        }

        $this->metadataPersistence->persist(
            $torrent,
            CanonicalTorrentMetadata::fromExtractedMetadata(
                $metadata,
                $data['type'] ?? null,
                $data['resolution'] ?? null,
                $data['source'] ?? null,
            ),
        );

        return $this->successfulUploadResponse(
            $torrent,
            is_array($decision->context['release_advice'] ?? null) ? $decision->context['release_advice'] : null,
            $this->metadataEnrichmentOutcomeFromContext($decision->context),
        );
    }

    private function mapDeniedEligibilityToApiResponse(UploadEligibilityDecision $decision): JsonResponse
    {
        if ($decision->reason === UploadEligibilityReason::DuplicateTorrent) {
            return $this->duplicateConflictResponse(
                $this->resolveDuplicateTorrentFromContext($decision->context),
                is_array($decision->context['release_advice'] ?? null) ? $decision->context['release_advice'] : null,
                $this->metadataEnrichmentOutcomeFromContext($decision->context),
            );
        }

        if ($decision->reason === UploadEligibilityReason::MissingMetadata) {
            throw ValidationException::withMessages([
                'torrent_file' => 'Invalid torrent payload: missing required metadata.',
            ]);
        }

        abort(403);
    }

    /**
     * @param  array<string, mixed>|null  $releaseAdvice
     */
    private function duplicateConflictResponse(?Torrent $existingTorrent = null, ?array $releaseAdvice = null, array $metadataEnrichmentOutcome = []): JsonResponse
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
     * @param  array<string, mixed>  $context
     */
    private function resolveDuplicateTorrentFromContext(array $context): ?Torrent
    {
        return $this->duplicateTorrentResolver->resolveFromContext($context);
    }

    /**
     * @param  array<string, mixed>|null  $releaseAdvice
     */
    private function successfulUploadResponse(Torrent $torrent, ?array $releaseAdvice = null, array $metadataEnrichmentOutcome = []): JsonResponse
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

    /**
     * @param  array<string, mixed>  $context
     * @return array{metadata_enrichment_applied_fields: list<string>, metadata_enrichment_conflicts: list<string>}
     */
    private function metadataEnrichmentOutcomeFromContext(array $context): array
    {
        return [
            'metadata_enrichment_applied_fields' => is_array($context['metadata_enrichment_applied_fields'] ?? null)
                ? array_values(array_filter($context['metadata_enrichment_applied_fields'], static fn (mixed $field): bool => is_string($field)))
                : [],
            'metadata_enrichment_conflicts' => is_array($context['metadata_enrichment_conflicts'] ?? null)
                ? array_values(array_filter($context['metadata_enrichment_conflicts'], static fn (mixed $field): bool => is_string($field)))
                : [],
        ];
    }
}
