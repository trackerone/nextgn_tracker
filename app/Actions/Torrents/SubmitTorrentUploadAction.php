<?php

declare(strict_types=1);

namespace App\Actions\Torrents;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\SecurityAuditLog;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Logging\AuditLogger;
use App\Services\Security\SanitizationService;
use App\Services\Torrents\CanonicalTorrentMetadata;
use App\Services\Torrents\DuplicateTorrentResolver;
use App\Services\Torrents\PersistTorrentMetadataService;
use App\Services\Torrents\TorrentIngestService;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadEligibilityService;
use App\Services\Torrents\UploadPreflightContextBuilderContract;
use App\Services\Uploads\NfoStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SubmitTorrentUploadAction
{
    public function __construct(
        private readonly SanitizationService $sanitizer,
        private readonly TorrentIngestService $ingestService,
        private readonly NfoStorageService $nfoStorage,
        private readonly PersistTorrentMetadataService $metadataPersistence,
        private readonly AuditLogger $auditLogger,
        private readonly UploadEligibilityService $uploadEligibility,
        private readonly UploadPreflightContextBuilderContract $preflightContextBuilder,
        private readonly DuplicateTorrentResolver $duplicateTorrentResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, UploadedFile $torrentFile, array $data, ?UploadedFile $nfoFile = null): SubmitTorrentUploadResult
    {
        $nfoPayload = $this->resolveNfoText($nfoFile, $data);
        $context = $this->preflightContextBuilder->forPayload($user, strval($torrentFile->get()), [
            'type' => $data['type'] ?? null,
            'resolution' => $data['resolution'] ?? null,
            'nfo_text' => $nfoPayload,
        ]);
        $decision = $this->uploadEligibility->evaluate($user, $context);
        $metadataEnrichmentOutcome = $this->metadataEnrichmentOutcomeFromContext($decision->context);

        if ($decision->allowed === false) {
            if ($decision->reason === UploadEligibilityReason::DuplicateTorrent) {
                return SubmitTorrentUploadResult::duplicate(
                    $this->duplicateTorrentResolver->resolveFromContext($decision->context),
                    is_array($decision->context['release_advice'] ?? null) ? $decision->context['release_advice'] : null,
                    $metadataEnrichmentOutcome,
                );
            }

            return SubmitTorrentUploadResult::denied($decision, $metadataEnrichmentOutcome);
        }

        $metadata = $context->extractedMetadata;
        $torrentSizeBytes = $torrentFile->getSize();
        $torrentMime = $torrentFile->getClientMimeType();

        try {
            $nfoStoragePath = $this->nfoStorage->store($metadata->rawNfo);
        } catch (RuntimeException) {
            SecurityAuditLog::log($user, 'torrent.upload.rejected', [
                'reason' => 'nfo_storage_failed',
            ]);

            throw ValidationException::withMessages([
                'nfo_file' => 'Unable to store the provided NFO payload at this time.',
            ]);
        }

        $torrent = null;

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
                'nfo_storage_path' => $nfoStoragePath,
                'imdb_id' => $data['imdb_id'] ?? $metadata->imdbId,
                'tmdb_id' => $data['tmdb_id'] ?? $metadata->tmdbId,
                'status' => Torrent::STATUS_PENDING,
            ]);
        } catch (TorrentAlreadyExistsException $exception) {
            $this->nfoStorage->delete($nfoStoragePath);

            SecurityAuditLog::log($user, 'torrent.upload.duplicate', [
                'existing_torrent_id' => $exception->torrent->getKey(),
                'info_hash' => $exception->torrent->info_hash,
            ]);

            return SubmitTorrentUploadResult::duplicate($exception->torrent, null, $metadataEnrichmentOutcome);
        } catch (InvalidArgumentException $exception) {
            $this->nfoStorage->delete($nfoStoragePath);

            SecurityAuditLog::log($user, 'torrent.upload.rejected', [
                'reason' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'torrent_file' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->cleanupFailedUpload($torrent, $nfoStoragePath);

            throw $exception;
        }

        try {
            $this->metadataPersistence->persist(
                $torrent,
                CanonicalTorrentMetadata::fromExtractedMetadata(
                    $metadata,
                    $data['type'] ?? null,
                    $data['resolution'] ?? null,
                    $data['source'] ?? null,
                ),
            );

            $this->auditLogger->log('torrent.created', $torrent, [
                'uploader_id' => $user->getKey(),
                'info_hash' => $torrent->info_hash ?? null,
            ]);

            SecurityAuditLog::log($user, 'torrent.upload', [
                'torrent_id' => $torrent->getKey(),
                'size_bytes' => $torrentSizeBytes,
                'mime' => $torrentMime,
                'nfo_present' => $nfoStoragePath !== null,
                'nfo_bytes' => $this->resolveNfoSize($nfoFile, $nfoPayload),
            ]);
        } catch (Throwable $exception) {
            $this->cleanupFailedUpload($torrent, $nfoStoragePath);

            throw $exception;
        }

        return SubmitTorrentUploadResult::submitted(
            $torrent,
            is_array($decision->context['release_advice'] ?? null) ? $decision->context['release_advice'] : null,
            $metadataEnrichmentOutcome,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveNfoText(?UploadedFile $file, array $data): ?string
    {
        if ($file instanceof UploadedFile) {
            return strval($file->get());
        }

        $text = $data['nfo_text'] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    private function resolveNfoSize(?UploadedFile $file, ?string $payload): ?int
    {
        if ($file instanceof UploadedFile) {
            return $file->getSize();
        }

        if ($payload === null) {
            return null;
        }

        return strlen($payload);
    }

    private function cleanupFailedUpload(?Torrent $torrent, ?string $nfoStoragePath): void
    {
        if ($torrent instanceof Torrent) {
            $this->ingestService->deletePersistedTorrent($torrent);
        }

        $this->nfoStorage->delete($nfoStoragePath);
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
