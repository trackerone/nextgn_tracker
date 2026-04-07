<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use Illuminate\Http\Request;

final class AnnouncePipeline
{
    public function __construct(
        private readonly AnnounceRequestNormalizer $normalizer,
        private readonly PasskeyUserResolver $userResolver,
        private readonly AnnounceClientGuard $clientGuard,
        private readonly TorrentAccessResolver $torrentResolver,
        private readonly PeerEventProcessor $peerEventProcessor,
    ) {}

    public function handle(Request $request, string $passkey): AnnounceResult
    {
        $resolvedUser = $this->userResolver->resolve($request, $passkey);
        if ($resolvedUser instanceof AnnounceResult) {
            return $resolvedUser;
        }

        $normalized = $this->normalizer->normalize($request);
        if ($normalized instanceof AnnounceResult) {
            return $normalized;
        }

        $clientGuardResult = $this->clientGuard->ensureAllowed($request, $resolvedUser);
        if ($clientGuardResult instanceof AnnounceResult) {
            return $clientGuardResult;
        }

        $torrent = $this->torrentResolver->resolve($request, $resolvedUser, $normalized->infoHash);
        if ($torrent instanceof AnnounceResult) {
            return $torrent;
        }

        return $this->peerEventProcessor->process($request, $resolvedUser, $torrent, $normalized);
    }
}
