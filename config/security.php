<?php

declare(strict_types=1);

return [
    'max_input_length' => (int) env('SECURITY_MAX_INPUT_LENGTH', 12000),
    'max_torrent_kilobytes' => (int) env('SECURITY_MAX_TORRENT_KB', 8192),
    'max_nfo_kilobytes' => (int) env('SECURITY_MAX_NFO_KB', 512),
    'allowed_html_tags' => [
        'strong',
        'em',
        'b',
        'i',
        'u',
        'p',
        'br',
        'ul',
        'ol',
        'li',
        'code',
        'pre',
        'blockquote',
    ],
    'forbidden_html_tags' => [
        'script',
        'iframe',
        'embed',
        'object',
        'svg',
        'math',
    ],
    'forbidden_attributes' => [
        'on*',
        'style',
        'formaction',
        'xlink:href',
    ],
];
