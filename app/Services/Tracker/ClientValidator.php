<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Data\TrackerClientInfo;

class ClientValidator
{
    /**
     * @var array<string, string>
     */
    private const CODE_MAP = [
        'UT' => 'uTorrent',
        'qB' => 'qBittorrent',
        'TR' => 'Transmission',
        'DE' => 'Deluge',
        'AZ' => 'Azureus',
        'LT' => 'libtorrent',
    ];

    public function validateClient(string $peerId): TrackerClientInfo
    {
        [$clientName, $clientVersion] = $this->extractClient($peerId);
        $clientName = $clientName !== '' ? $clientName : 'Unknown';
        $clientVersion = $clientVersion ?: null;

        $signature = trim($clientName.($clientVersion ? '/'.$clientVersion : ''));

        $allowedPatterns = config('tracker_clients.allowed_clients', []);
        $bannedPatterns = config('tracker_clients.banned_clients', []);
        $minVersions = config('tracker_clients.min_client_version', []);

        $isBanned = $this->matchesAny($signature, $bannedPatterns) || $this->matchesAny($peerId, $bannedPatterns);
        $isAllowed = $allowedPatterns === []
            ? true
            : ($this->matchesAny($signature, $allowedPatterns) || $this->matchesAny($peerId, $allowedPatterns));

        if (isset($minVersions[$clientName]) && $clientVersion !== null) {
            if (version_compare($clientVersion, (string) $minVersions[$clientName], '<')) {
                $isBanned = true;
                $isAllowed = false;
            }
        }

        if ($isBanned) {
            $isAllowed = false;
        }

        return new TrackerClientInfo(
            clientName: $clientName,
            clientVersion: $clientVersion,
            isAllowed: $isAllowed,
            isBanned: $isBanned,
        );
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function extractClient(string $peerId): array
    {
        if (preg_match('/^-([A-Za-z0-9]{2})(\d{4})-/', $peerId, $matches) === 1) {
            $code = $matches[1];
            $digits = $matches[2];
            $name = self::CODE_MAP[$code] ?? $code;

            return [$name, $this->normaliseVersionDigits($digits)];
        }

        if (preg_match('/^(?<name>[A-Za-z]+)(?<version>\d+\.\d+(?:\.\d+)*)/', $peerId, $matches) === 1) {
            return [$matches['name'], $matches['version']];
        }

        return ['', null];
    }

    private function normaliseVersionDigits(string $digits): string
    {
        $parts = array_map(static fn (string $digit): int => (int) $digit, str_split($digits));

        return implode('.', $parts);
    }

    /**
     * @param array<int, string> $patterns
     */
    private function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
