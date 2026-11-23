<?php

declare(strict_types=1);

return [
    'max_input_length' => (int) env('SECURITY_MAX_INPUT_LENGTH', 12000),
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

    'admin_two_factor' => [
        'required' => (bool) env('ADMIN_TWO_FACTOR_REQUIRED', true),
        'setup_authorization_ttl_seconds' => 600,
    ],

    'roles' => [
        'sysop',
        'admin',
        'moderator',
        'uploader',
        'power_user',
        'user',
        'newbie',
        'guest',
    ],

    'permissions' => [
        'admin.access',
        'admin.manage.users',
        'torrent.upload',
        'torrent.browse',
        'torrent.download',
        'rss.access',
        'watch.access',
        'invites.create',
        'torrent.edit',
        'torrent.delete',
        'comment.post',
        'comment.delete',
        'api.access',
    ],

    'role_permissions' => [
        'sysop' => [
            'torrent.browse', 'torrent.download', 'rss.access', 'watch.access',
            'admin.access', 'admin.manage.users', 'torrent.upload', 'torrent.edit',
            'torrent.delete', 'forum.access', 'messages.access', 'invites.create', 'comment.post',
            'comment.delete', 'api.access',
        ],
        'admin' => [
            'torrent.browse', 'torrent.download', 'rss.access', 'watch.access',
            'admin.access', 'admin.manage.users', 'torrent.upload', 'torrent.edit',
            'torrent.delete', 'forum.access', 'messages.access', 'invites.create', 'comment.post',
            'comment.delete', 'api.access',
        ],
        'moderator' => [
            'torrent.browse', 'torrent.download', 'rss.access', 'watch.access',
            'torrent.upload', 'torrent.edit', 'torrent.delete', 'forum.access',
            'messages.access', 'invites.create', 'comment.delete', 'api.access',
        ],
        'uploader' => [
            'torrent.browse', 'torrent.download', 'rss.access', 'watch.access',
            'torrent.upload', 'forum.access', 'messages.access', 'comment.post', 'api.access',
        ],
        'power_user' => [
            'torrent.browse', 'torrent.download', 'rss.access', 'watch.access',
            'forum.access', 'messages.access', 'comment.post', 'api.access',
        ],
        'user' => [
            'torrent.browse', 'torrent.download', 'rss.access', 'watch.access',
            'forum.access', 'messages.access', 'comment.post', 'api.access',
        ],
        'newbie' => [
            'torrent.browse', 'torrent.download', 'rss.access', 'watch.access',
            'api.access',
        ],
        'guest' => [],
    ],

    'rate_limits' => [
        'login' => env('SECURITY_RATE_LIMIT_LOGIN', '5,1'),
        'login_account' => env('SECURITY_RATE_LIMIT_LOGIN_ACCOUNT', '20,60'),
        'login_ip' => env('SECURITY_RATE_LIMIT_LOGIN_IP', '100,60'),
        'admin_two_factor' => env('SECURITY_RATE_LIMIT_ADMIN_TWO_FACTOR', '5,1'),
        'admin_two_factor_account' => env('SECURITY_RATE_LIMIT_ADMIN_TWO_FACTOR_ACCOUNT', '20,60'),
        'admin_two_factor_ip' => env('SECURITY_RATE_LIMIT_ADMIN_TWO_FACTOR_IP', '100,60'),
        'register' => '3,60',
        'password_reset' => '3,60',
        'torrent_upload' => '10,60',
        'api' => '60,1',
        'admin' => '30,1',
        'search' => '30,1',
        'torrent_browse' => '60,1',
        'torrent_details' => '90,1',
        'torrent_download' => '45,1',
        'moderation' => '60,1',
        'torrent_moderation' => env('SECURITY_RATE_LIMIT_TORRENT_MODERATION', env('SECURITY_RATE_LIMIT_MODERATION', '60,1')),
    ],

    'production_hardening_enabled' => (bool) env('NEXTGN_PRODUCTION_HARDENING', false),

    'api' => [
        'allow_legacy_keys' => (bool) env('SECURITY_API_ALLOW_LEGACY_KEYS', true),
        'hmac_secret' => env('API_HMAC_SECRET'),
        'allowed_time_skew_seconds' => (int) env('API_ALLOWED_TIME_SKEW', 120),
        'require_nonce' => (bool) env('API_REQUIRE_NONCE', true),
        'default_rate_limit' => env('API_DEFAULT_RATE_LIMIT', env('SECURITY_RATE_LIMIT_API', '60,1')),
    ],
];
