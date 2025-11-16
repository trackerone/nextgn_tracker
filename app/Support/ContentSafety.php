<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\MarkdownService;

final class ContentSafety
{
    private const MARKDOWN_ALLOWED_TAGS = '<p><br><strong><em><ul><ol><li><code><pre><blockquote><a><span>';

    private const NFO_MAX_OUTPUT_BYTES = 65536;

    public static function e(?string $value): string
    {
        return e($value ?? '');
    }

    public static function markdownToSafeHtml(?string $value): string
    {
        $value = $value ?? '';
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        /** @var MarkdownService $markdown */
        $markdown = app(MarkdownService::class);
        $html = $markdown->render($value);
        $html = strip_tags($html, self::MARKDOWN_ALLOWED_TAGS);
        $html = preg_replace('/\son[a-z0-9_-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace_callback(
            '/\s(href|src)\s*=\s*("|\')(.*?)\2/i',
            static function (array $matches): string {
                $attribute = strtolower($matches[1]);
                $quote = $matches[2];
                $url = trim($matches[3]);
                $lower = strtolower($url);

                if ($lower === '' || str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
                    return sprintf(' %s=%s#%2$s', $attribute, $quote);
                }

                return sprintf(' %s=%s%s%s', $attribute, $quote, $url, $quote);
            },
            $html,
        ) ?? $html;

        return $html;
    }

    public static function nfoToSafeText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $value);

        if (strlen($normalized) > self::NFO_MAX_OUTPUT_BYTES) {
            $normalized = substr($normalized, 0, self::NFO_MAX_OUTPUT_BYTES);
        }

        return self::e($normalized);
    }
}
