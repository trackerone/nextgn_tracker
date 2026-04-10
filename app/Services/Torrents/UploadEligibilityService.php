<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use Illuminate\Support\Str;

final class UploadEligibilityService
{
    public function __construct(
        private readonly UploadEligibilityTelemetryService $telemetry,
        private readonly BencodeService $bencode,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function canUpload(User $user, array $context = []): bool
    {
        return $this->decide($user, $context)->allowed;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(User $user, array $context = []): UploadEligibilityDecision
    {
        return $this->record($user, $this->decide($user, $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluateForPayload(User $user, string $torrentPayload, array $context = []): UploadEligibilityDecision
    {
        return $this->record($user, $this->decideForPayload($user, $torrentPayload, $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function decide(User $user, array $context = []): UploadEligibilityDecision
    {
        $decisionContext = $this->buildDecisionContext($user, $context);

        return $this->applyUserRestrictions($user, $decisionContext);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function decideForPayload(User $user, string $torrentPayload, array $context = []): UploadEligibilityDecision
    {
        $decisionContext = array_merge(
            $this->buildDecisionContext($user, $context),
            $this->buildPayloadDecisionContext($torrentPayload),
        );

        $userDecision = $this->applyUserRestrictions($user, $decisionContext);
        if (! $userDecision->allowed) {
            return $userDecision;
        }

        if (($decisionContext['metadata_complete'] ?? true) === false) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::MissingMetadata, $decisionContext);
        }

        if (($decisionContext['duplicate'] ?? false) === true) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::DuplicateTorrent, $decisionContext);
        }

        return UploadEligibilityDecision::allow($decisionContext);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildDecisionContext(User $user, array $context): array
    {
        return array_filter([
            'category' => $this->asStringOrNull($context['category'] ?? null),
            'type' => $this->asStringOrNull($context['type'] ?? null),
            'resolution' => $this->asStringOrNull($context['resolution'] ?? null),
            'scene' => $this->asBoolOrNull($context['scene'] ?? null),
            'duplicate' => $this->asBoolOrNull($context['duplicate'] ?? null),
            'size' => $this->asIntOrNull($context['size'] ?? null),
            'is_banned' => $user->isBanned(),
            'is_disabled' => $user->isDisabled(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function asStringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function asBoolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private function asIntOrNull(mixed $value): ?int
    {
        if (! is_int($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $decisionContext
     */
    private function applyUserRestrictions(User $user, array $decisionContext): UploadEligibilityDecision
    {
        if ($user->isBanned()) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::UserBanned, $decisionContext);
        }

        if ($user->isDisabled()) {
            return UploadEligibilityDecision::deny(UploadEligibilityReason::UserDisabled, $decisionContext);
        }

        return UploadEligibilityDecision::allow($decisionContext);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadDecisionContext(string $torrentPayload): array
    {
        $decoded = $this->bencode->decode($torrentPayload);
        if (! is_array($decoded)) {
            return ['metadata_complete' => false];
        }

        $info = $decoded['info'] ?? null;
        if (! is_array($info)) {
            return ['metadata_complete' => false];
        }

        $sizeBytes = $this->extractSizeBytes($info);
        if ($sizeBytes === null) {
            return ['metadata_complete' => false];
        }

        $infoHash = Str::upper(sha1($this->bencode->encode($info)));
        $existingTorrent = Torrent::query()
            ->select(['id'])
            ->where('info_hash', $infoHash)
            ->first();

        return array_filter([
            'metadata_complete' => true,
            'size' => $sizeBytes,
            'info_hash' => $infoHash,
            'duplicate' => $existingTorrent !== null,
            'existing_torrent_id' => $existingTorrent?->getKey(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function extractSizeBytes(array $info): ?int
    {
        if (isset($info['length']) && is_numeric($info['length'])) {
            return (int) $info['length'];
        }

        $files = $info['files'] ?? null;
        if (! is_array($files) || $files === []) {
            return null;
        }

        $total = 0;

        foreach ($files as $file) {
            if (! is_array($file) || ! isset($file['length']) || ! is_numeric($file['length'])) {
                return null;
            }

            $total += (int) $file['length'];
        }

        return $total;
    }

    private function record(User $user, UploadEligibilityDecision $decision): UploadEligibilityDecision
    {
        $this->telemetry->record($user, $decision);

        return $decision;
    }
}
