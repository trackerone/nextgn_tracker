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
        public readonly ?int $year,
    ) {}

    public static function fromQuery(string $query): self
    {
        $tokens = preg_split('/\s+/', trim($query)) ?: [];

        $textTokens = [];
        $releaseGroup = null;
        $source = null;
        $resolution = null;
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
                    $releaseGroup = mb_strtoupper($value);
                    break;

                case 'source':
                case 'src':
                    $source = mb_strtoupper($value);
                    break;

                case 'resolution':
                case 'res':
                    $resolution = mb_strtolower($value);
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
            year: $year,
        );
    }

    public function hasMetadataDirectives(): bool
    {
        return $this->releaseGroup !== null
            || $this->source !== null
            || $this->resolution !== null
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
}
