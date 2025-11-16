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

    'lockdown' => (bool) env('SECURITY_LOCKDOWN', false),

    'roles' => [
        'admin',
        'moderator',
        'uploader',
        'user',
        'guest',
    ],

    'permissions' => [
        'admin.access',
        'admin.manage.users',
        'torrent.upload',
        'torrent.edit',
        'torrent.delete',
        'comment.post',
        'comment.delete',
        'api.access',
    ],

    'role_permissions' => [
        'admin' => [
            'admin.access',
            'admin.manage.users',
            'torrent.upload',
            'torrent.edit',
            'torrent.delete',
            'comment.post',
            'comment.delete',
            'api.access',
        ],
        'moderator' => [
            'torrent.edit',
            'torrent.delete',
            'comment.delete',
            'api.access',
        ],
        'uploader' => [
            'torrent.upload',
            'comment.post',
            'api.access',
        ],
        'user' => [
            'comment.post',
            'api.access',
        ],
        'guest' => [],
    ],

    'rate_limits' => [
        'login' => '5,1',
        'register' => '3,60',
        'password_reset' => '3,60',
        'torrent_upload' => '10,60',
        'api' => '60,1',
        'admin' => '30,1',
        'search' => '30,1',
    ],

    'api' => [
        'hmac_secret' => env('API_HMAC_SECRET'),
        'allowed_time_skew_seconds' => (int) env('API_ALLOWED_TIME_SKEW', 300),
        'default_rate_limit' => env('API_DEFAULT_RATE_LIMIT', '60,1'),
    ],
];
