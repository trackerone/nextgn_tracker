<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Normalizer;

final class SanitizationService
{
    private readonly array $allowedTags;
    private readonly string $allowedTagString;
    private readonly array $forbiddenTags;
    private readonly array $forbiddenAttributes;
    private readonly int $maxInputLength;

    public function __construct()
    {
        $this->allowedTags = config('security.allowed_html_tags', []);
        $this->allowedTagString = $this->buildAllowedTagString($this->allowedTags);
        $this->forbiddenTags = config('security.forbidden_html_tags', []);
        $this->forbiddenAttributes = config('security.forbidden_attributes', []);
        $this->maxInputLength = (int) config('security.max_input_length', 12000);
    }

    public function sanitizeInput(array|string|null $value): array|string|null
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        return $this->sanitizeString($value);
    }

    public function sanitizeString(string $value): string
    {
        $value = $this->normalizeEncoding($value);
        $value = $this->limitLength($value);
        $value = $this->removeNullBytes($value);
        $value = $this->removeForbiddenElements($value);
        $value = $this->stripDisallowedTags($value);
        $value = $this->stripForbiddenAttributes($value);
        $value = $this->neutralizeJavascriptProtocols($value);

        return trim($value);
    }

    public function sanitizeHtmlDocument(string $value, array $excludedAttributes = []): string
    {
        $value = $this->normalizeEncoding($value);
        $value = $this->removeForbiddenElements($value, ['script']);
        $value = $this->stripForbiddenAttributes($value, $excludedAttributes);
        $value = $this->neutralizeJavascriptProtocols($value);

        return $value;
    }

    private function sanitizeArray(array $value): array
    {
        $sanitized = [];

        foreach ($value as $key => $item) {
            if ($item instanceof UploadedFile) {
                $sanitized[$key] = $item;
                continue;
            }

            if (is_array($item)) {
                $sanitized[$key] = $this->sanitizeArray($item);
                continue;
            }

            if (is_string($item)) {
                $sanitized[$key] = $this->sanitizeString($item);
                continue;
            }

            $sanitized[$key] = $item;
        }

        return $sanitized;
    }

    private function buildAllowedTagString(array $tags): string
    {
        if ($tags === []) {
            return '';
        }

        return '<'.implode('><', $tags).'>';
    }

    private function normalizeEncoding(string $value): string
    {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        if (class_exists(Normalizer::class) && Normalizer::isNormalized($value) === false) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?? $value;
        }

        return $value;
    }

    private function limitLength(string $value): string
    {
        if ($this->maxInputLength <= 0) {
            return $value;
        }

        if (mb_strlen($value, 'UTF-8') <= $this->maxInputLength) {
            return $value;
        }

        return mb_substr($value, 0, $this->maxInputLength, 'UTF-8');
    }

    private function removeNullBytes(string $value): string
    {
        return str_replace("\0", '', $value);
    }

    private function removeForbiddenElements(string $value, array $exceptions = []): string
    {
        if ($this->forbiddenTags === []) {
            return $value;
        }

        foreach ($this->forbiddenTags as $tag) {
            if (in_array($tag, $exceptions, true)) {
                continue;
            }
            $escaped = preg_quote($tag, '/');
            $patternWithContent = sprintf("/<%1$s\b[^>]*>.*?<\/%1$s>/is", $escaped);
            $patternSelfClosing = sprintf("/<%s\b[^>]*\/>/i", $escaped);
            $patternOpening = sprintf("/<%s\b[^>]*>/i", $escaped);
            $patternClosing = sprintf("/<\/%s>/i", $escaped);

            $value = preg_replace($patternWithContent, '', $value) ?? $value;
            $value = preg_replace($patternSelfClosing, '', $value) ?? $value;
            $value = preg_replace($patternOpening, '', $value) ?? $value;
            $value = preg_replace($patternClosing, '', $value) ?? $value;
        }

        return $value;
    }

    private function stripDisallowedTags(string $value): string
    {
        if ($this->allowedTagString === '') {
            return strip_tags($value);
        }

        return strip_tags($value, $this->allowedTagString);
    }

    private function stripForbiddenAttributes(string $value, array $excluded = []): string
    {
        foreach ($this->forbiddenAttributes as $attribute) {
            if (in_array($attribute, $excluded, true)) {
                continue;
            }
            if ($attribute === 'on*') {
                $value = preg_replace("/\son[a-z0-9_-]+\s*=\s*(\"[^\"]*\"|'[^']*'|[^\s>]+)/i", '', $value) ?? $value;
                continue;
            }

            $escaped = preg_quote($attribute, '/');
            $pattern = sprintf("/\s%s\s*=\s*(\"[^\"]*\"|'[^']*'|[^\s>]+)/i", $escaped);
            $value = preg_replace($pattern, '', $value) ?? $value;
        }

        return $value;
    }

    private function neutralizeJavascriptProtocols(string $value): string
    {
        $value = preg_replace_callback(
            "\b(href|src)\s*=\s*(\"|')(.*?)\2/i",
            function (array $matches): string {
                $attribute = strtolower($matches[1]);
                $url = trim($matches[3]);
                $lower = strtolower($url);

                if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:text/html')) {
                    return sprintf('%s="%s"', $attribute, '#');
                }

                return $matches[0];
            },
            $value,
        ) ?? $value;

        $value = preg_replace("/javascript\s*:/i", '', $value) ?? $value;

        return $value;
    }
}
