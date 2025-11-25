<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use function e;

class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $markdown = trim($markdown);

        if ($markdown === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $html = '';
        $inList = false;
        $codeBuffer = null;

        foreach ($lines as $line) {
            if (Str::startsWith($line, '```')) {
                if ($codeBuffer === null) {
                    $codeBuffer = [];
                } else {
                    $html .= sprintf('<pre><code>%s</code></pre>', e(implode("\n", $codeBuffer)));
                    $codeBuffer = null;
                }

                continue;
            }

            if ($codeBuffer !== null) {
                $codeBuffer[] = $line;

                continue;
            }

            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }

                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.*)$/', $trimmed, $matches) === 1) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }

                $level = strlen($matches[1]);
                $html .= sprintf('<h%d>%s</h%d>', $level, $this->inline($matches[2]), $level);

                continue;
            }

            if (preg_match('/^\s*([-*])\s+(.*)$/', $line, $matches) === 1) {
                if ($inList === false) {
                    $html .= '<ul>';
                    $inList = true;
                }

                $html .= sprintf('<li>%s</li>', $this->inline($matches[2]));

                continue;
            }

            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches) === 1) {
                $html .= sprintf('<blockquote>%s</blockquote>', $this->inline($matches[1]));

                continue;
            }

            $html .= sprintf('<p>%s</p>', $this->inline($trimmed));
        }

        if ($inList) {
            $html .= '</ul>';
        }

        if ($codeBuffer !== null) {
            $html .= sprintf('<pre><code>%s</code></pre>', e(implode("\n", $codeBuffer)));
        }

        return $html;
    }

    private function inline(string $text): string
    {
        $escaped = e($text);

        $replacements = [
            '/\*\*(.+?)\*\*/s' => '<strong>$1</strong>',
            '/__(.+?)__/s' => '<strong>$1</strong>',
            '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s' => '<em>$1</em>',
            '/_(.+?)_/s' => '<em>$1</em>',
            '/`(.+?)`/s' => '<code>$1</code>',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $escaped = preg_replace($pattern, $replacement, $escaped) ?? $escaped;
        }

        $escaped = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function (array $matches): string {
            $url = e($matches[2]);
            $label = e($matches[1]);

            return sprintf('<a href="%s">%s</a>', $url, $label);
        }, $escaped) ?? $escaped;

        $escaped = preg_replace_callback('/((?:https?:\/\/)[^\s<]+)/i', static function (array $matches): string {
            $url = e($matches[1]);

            return sprintf('<a href="%s">%s</a>', $url, $url);
        }, $escaped) ?? $escaped;

        return $escaped;
    }
}
