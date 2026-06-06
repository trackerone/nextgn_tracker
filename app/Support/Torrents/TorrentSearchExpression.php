<?php

declare(strict_types=1);

namespace App\Support\Torrents;

final class TorrentSearchExpression
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $releaseGroup,
        public readonly ?string $source,
        public readonly ?string $resolution,
        public readonly ?string $language,
        public readonly ?string $audioLanguage,
        public readonly ?string $subtitleLanguage,
        public readonly ?int $year,
    ) {}

    public static function fromQuery(string $query): self
    {
        $tokens = preg_split('/\s+/', trim($query)) ?: [];

        $textTokens = [];
        $releaseGroup = null;
        $source = null;
        $resolution = null;
        $language = null;
        $audioLanguage = null;
        $subtitleLanguage = null;
        $year = null;

        foreach ($tokens as $token) {
            $pair = self::directivePair($token);

            if ($pair === null) {
                $textTokens[] = $token;

                continue;
            }

            [$key, $value] = $pair;

            if ($value === '') {
                continue;
            }

            switch ($key) {
                case 'rg':
                case 'group':
                case 'release_group':
                    $releaseGroup = self::normalizeUppercase($value);
                    break;

                case 'source':
                case 'src':
                    $source = self::normalizeUppercase($value);
                    break;

                case 'resolution':
                case 'res':
                    $resolution = self::normalizeLowercase($value);
                    break;

                case 'lang':
                case 'language':
                    $language = self::normalizeLowercase($value);
                    break;

                case 'audio':
                case 'audio_language':
                    $audioLanguage = self::normalizeLowercase($value);
                    break;

                case 'sub':
                case 'subtitle_language':
                    $subtitleLanguage = self::normalizeCommaSeparatedLowercase($value);
                    break;

                case 'year':
                    if (ctype_digit($value)) {
                        $yearValue = (int) $value;

                        if ($yearValue >= 1900 && $yearValue <= 2100) {
                            $year = $yearValue;
                        }
                    }
                    break;

                default:
                    $textTokens[] = $token;
                    break;
            }
        }

        return new self(
            text: trim(implode(' ', $textTokens)),
            releaseGroup: $releaseGroup,
            source: $source,
            resolution: $resolution,
            language: $language,
            audioLanguage: $audioLanguage,
            subtitleLanguage: $subtitleLanguage,
            year: $year,
        );
    }

    public function hasMetadataDirectives(): bool
    {
        return $this->releaseGroup !== null
            || $this->source !== null
            || $this->resolution !== null
            || $this->language !== null
            || $this->audioLanguage !== null
            || $this->subtitleLanguage !== null
            || $this->year !== null;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function directivePair(string $token): ?array
    {
        $separatorPosition = strpos($token, ':');

        if ($separatorPosition === false) {
            return null;
        }

        $key = mb_strtolower(trim(substr($token, 0, $separatorPosition)));
        $value = trim(substr($token, $separatorPosition + 1), "\"' ");

        if ($key === '') {
            return null;
        }

        return [$key, $value];
    }

    private static function normalizeUppercase(string $value): ?string
    {
        $normalized = trim(mb_strtoupper($value));

        return $normalized !== '' ? $normalized : null;
    }

    private static function normalizeLowercase(string $value): ?string
    {
        $normalized = trim(mb_strtolower($value));

        return $normalized !== '' ? $normalized : null;
    }

    private static function normalizeCommaSeparatedLowercase(string $value): ?string
    {
        $parts = array_map(static fn (string $part): string => trim(mb_strtolower($part)), explode(',', $value));
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return null;
        }

        return implode(',', $parts);
    }
}
