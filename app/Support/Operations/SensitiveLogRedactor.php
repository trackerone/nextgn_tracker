<?php

declare(strict_types=1);

namespace App\Support\Operations;

final class SensitiveLogRedactor
{
    private const MASK = '[REDACTED]';

    public function text(?string $value): string
    {
        $text = (string) $value;

        // Passkeys are embedded in tracker URLs; never echo them back to the browser.
        $text = preg_replace('~(?<![A-Za-z0-9])(/?(?:announce|scrape|rss)/)[A-Za-z0-9]{8,}~i', '$1'.self::MASK, $text) ?? self::MASK;

        // Also hide path-segment secrets in encoded tracker URLs and passkey/token routes.
        // A captured request path must not expose a reusable credential in technical context.
        $text = preg_replace(
            '#((?:/|%2f)(?:announce|scrape|rss|passkey|token|api[_-]?key)(?:/|%2f))[A-Za-z0-9._~-]{8,}#i',
            '$1'.self::MASK,
            $text
        ) ?? self::MASK;

        // Cover both HTTP header and common key=value / JSON-style diagnostics.
        $text = preg_replace('#(Bearer\s+)[A-Za-z0-9._~+/-]+=*#i', '$1'.self::MASK, $text) ?? self::MASK;
        $text = preg_replace(
            '~((?:password|passwd|passkey|api[_-]?key|access[_-]?token|refresh[_-]?token|secret|authorization|cookie|session[_-]?id|token)\s*["\x27]?\s*[:=]\s*["\x27]?)[^\s,"\x27;&}\]]+~i',
            '$1'.self::MASK,
            $text
        ) ?? self::MASK;
        $text = preg_replace('~([?&](?:token|passkey|api_key|key|auth|signature|secret)=)[^&\s]+~i', '$1'.self::MASK, $text) ?? self::MASK;

        // A log viewer is not a general-purpose secret dump.
        return mb_substr($text, 0, 1500);
    }

    public function value(mixed $value): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[$key] = is_string($key) && preg_match(
                    '/(?:password|passwd|passkey|secret|token|api.?key|authorization|cookie|session|credential|private.?key)/i',
                    $key
                ) ? self::MASK : $this->value($item);
            }

            return $clean;
        }

        if (is_string($value)) {
            return $this->text($value);
        }

        return $value;
    }
}
