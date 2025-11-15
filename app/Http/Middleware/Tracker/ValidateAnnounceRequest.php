<?php

declare(strict_types=1);

namespace App\Http\Middleware\Tracker;

use App\Models\Peer;
use App\Models\User;
use App\Services\Logging\SecurityEventLogger;
use App\Services\Tracker\ClientValidator;
use App\Services\Tracker\FailureResponder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ValidateAnnounceRequest
{
    public function __construct(
        private readonly FailureResponder $failureResponder,
        private readonly ClientValidator $clientValidator,
        private readonly SecurityEventLogger $securityLogger,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $passkey = (string) $request->route('passkey', '');
        if ($passkey === '' || strlen($passkey) !== 64 || ! ctype_xdigit($passkey)) {
            $this->logInvalidPasskey($request, $passkey);

            return $this->failureResponder->fail('invalid_passkey');
        }
        $user = User::query()->where('passkey', $passkey)->first();
        if ($user === null) {
            $this->logInvalidPasskey($request, $passkey);

            return $this->failureResponder->fail('invalid_passkey');
        }
        if ($user->isBanned() || $user->isDisabled()) {
            return $this->failureResponder->fail('unauthorized_client');
        }
        $now = now();
        $minInterval = (int) config('tracker.announce_min_interval_seconds', 30);
        if ($user->announce_rate_limit_exceeded) {
            $cooldownElapsed = $user->last_announce_at === null
                || $user->last_announce_at->diffInSeconds($now) >= $minInterval;

            if ($user->isStaff() || $cooldownElapsed) {
                $user->forceFill(['announce_rate_limit_exceeded' => false])->save();
            } else {
                $this->securityLogger->log('tracker.rate_limited', 'medium', 'Announce blocked due to persistent rate limit.', [
                    'user_id' => $user->getKey(),
                    'ip' => $request->ip(),
                ]);

                return $this->failureResponder->fail('rate_limit');
            }
        }
        $params = $request->query();
        $required = ['info_hash', 'peer_id', 'port', 'uploaded', 'downloaded', 'left'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $params)) {
                return $this->failureResponder->fail('invalid_parameters');
            }
        }
        foreach ($params as $value) {
            if (is_array($value)) {
                return $this->failureResponder->fail('invalid_parameters');
            }
            if (is_string($value) && strlen($value) > 255) {
                return $this->failureResponder->fail('invalid_parameters');
            }
        }
        $infoHash = $this->normaliseInfoHash($request->query('info_hash'));
        if ($infoHash === null) {
            return $this->failureResponder->fail('invalid_parameters');
        }
        $peerId = $request->query('peer_id');
        if (! is_string($peerId) || strlen($peerId) !== 20) {
            return $this->failureResponder->fail('invalid_parameters');
        }
        $port = $this->parseIntegerParam($request, 'port', 1, 65535);
        $uploaded = $this->parseIntegerParam($request, 'uploaded', 0, null, true);
        $downloaded = $this->parseIntegerParam($request, 'downloaded', 0, null, true);
        $left = $this->parseIntegerParam($request, 'left', 0, null, true);
        if ($port === null || $uploaded === null || $downloaded === null || $left === null) {
            return $this->failureResponder->fail('invalid_parameters');
        }
        $clientInfo = $this->clientValidator->validateClient($peerId);
        if ($clientInfo->isBanned) {
            $this->securityLogger->log('tracker.client_banned', 'high', 'Banned client attempted announce.', [
                'peer_id' => $peerId,
                'info_hash' => bin2hex($infoHash),
                'client' => $clientInfo->clientName,
                'version' => $clientInfo->clientVersion,
                'user_id' => $user->getKey(),
            ]);

            return $this->failureResponder->fail('client_banned');
        }
        if (! $clientInfo->isAllowed) {
            $this->securityLogger->log('tracker.unauthorized_client', 'medium', 'Client not allowed to announce.', [
                'peer_id' => $peerId,
                'info_hash' => bin2hex($infoHash),
                'client' => $clientInfo->clientName,
                'version' => $clientInfo->clientVersion,
                'user_id' => $user->getKey(),
            ]);

            return $this->failureResponder->fail('unauthorized_client');
        }
        $ipAddress = $this->resolveIp($request);
        if ($ipAddress === null) {
            return $this->failureResponder->fail('invalid_parameters');
        }
        if (! $user->isStaff() && $user->last_announce_at !== null) {
            $diff = $user->last_announce_at->diffInSeconds($now);
            if ($diff < $minInterval) {
                $user->forceFill(['announce_rate_limit_exceeded' => true])->save();

                $this->securityLogger->log('tracker.rate_limited', 'medium', 'Announce frequency exceeded limit.', [
                    'user_id' => $user->getKey(),
                    'seconds_since_last' => $diff,
                    'ip' => $ipAddress,
                ]);

                return $this->failureResponder->fail('rate_limit');
            }
        }
        $ghostTimeout = (int) config('tracker.ghost_peer_timeout_minutes', 45);
        $existingPeer = Peer::query()
            ->where('user_id', $user->getKey())
            ->where('peer_id', $peerId)
            ->orderByDesc('last_announce_at')
            ->first();
        $peerExpired = $existingPeer !== null
            && $existingPeer->last_announce_at !== null
            && $existingPeer->last_announce_at->lt($now->copy()->subMinutes($ghostTimeout));
        $request->attributes->set('tracker.user', $user);
        $request->attributes->set('tracker.info_hash_binary', $infoHash);
        $request->attributes->set('tracker.info_hash_hex', strtoupper(bin2hex($infoHash)));
        $request->attributes->set('tracker.peer_id', $peerId);
        $request->attributes->set('tracker.client', $clientInfo);
        $request->attributes->set('tracker.port', $port);
        $request->attributes->set('tracker.uploaded', $uploaded);
        $request->attributes->set('tracker.downloaded', $downloaded);
        $request->attributes->set('tracker.left', $left);
        $request->attributes->set('tracker.ip', $ipAddress);
        $request->attributes->set('tracker.peer_expired', $peerExpired);
        $request->attributes->set('tracker.existing_peer_id', $existingPeer?->getKey());

        /** @var Response $response */
        $response = $next($request);
        $user->forceFill([
            'last_announce_at' => $now,
            'announce_rate_limit_exceeded' => false,
        ])->save();
        return $response;
    }

    private function normaliseInfoHash(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        if (strlen($value) === 20) {
            return $value;
        }

        if (preg_match('/^[0-9a-fA-F]{40}$/', $value) === 1) {
            return hex2bin($value) ?: null;
        }

        return null;
    }

    private function parseIntegerParam(Request $request, string $key, int $min, ?int $max, bool $logNegative = false): ?int
    {
        $value = $request->query($key);

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || $value === '' || preg_match('/^-?\d+$/', $value) !== 1) {
            return null;
        }

        $intValue = (int) $value;

        if ($intValue < $min) {
            if ($logNegative && $intValue < 0) {
                $this->securityLogger->log('tracker.invalid_stats', 'medium', 'Negative statistic detected in announce payload.', [
                    'param' => $key,
                    'value' => $value,
                ]);
            }

            return null;
        }

        if ($max !== null && $intValue > $max) {
            return null;
        }

        return $intValue;
    }

    private function resolveIp(Request $request): ?string
    {
        $paramIp = $request->query('ip');
        $remoteIp = $request->ip();

        if ($paramIp === null || $paramIp === '') {
            return $remoteIp ?? '0.0.0.0';
        }

        if (! is_string($paramIp) || filter_var($paramIp, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if ($remoteIp !== null && $paramIp !== $remoteIp) {
            return null;
        }

        return $paramIp;
    }

    private function logInvalidPasskey(Request $request, string $passkey): void
    {
        $this->securityLogger->log('tracker.invalid_passkey', 'medium', 'Invalid passkey used during announce.', [
            'passkey_suffix' => $passkey !== '' ? substr($passkey, -8) : null,
            'ip' => $request->ip(),
        ]);
    }
}
