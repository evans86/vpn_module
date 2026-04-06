<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'api_keys' => [
        'vdsina_key' => env('VDSINA_API_KEY'),
        'timeweb_key' => env('TIMEWEB_API_KEY'),
        /** Токен другого аккаунта Timeweb (старые сервера), если TIMEWEB_API_KEY — новый аккаунт */
        'timeweb_key_legacy' => env('TIMEWEB_API_KEY_LEGACY'),
    ],

    'timeweb' => [
        'project_id' => env('TIMEWEB_PROJECT_ID'), // ID проекта (например VPN-TELEGRAM) — в панели в URL или в карточке проекта
    ],

    'cloudflare' => [
        'email' => env('CLOUDFLARE_EMAIL', 'support@bot-t.ru'),
        'api_key' => env('CLOUDFLARE_API_KEY', '1697f393d7d2fceb7866b0c7062d025b8cfe6'),
        /** @deprecated Используйте legacy_zone_id; оставлено для совместимости */
        'zone_id' => env('CLOUDFLARE_ZONE_ID', 'ecd4115fa760df3dd0a5f9c0e2caee2d'),
        /**
         * Зона vpn-telegram.com: только для удаления старых DNS-записей, если у сервера не задан cloudflare_zone_id.
         * Новые A-записи создаются только в dns_zones (случайная зона из пула).
         */
        'legacy_zone_id' => env('CLOUDFLARE_LEGACY_ZONE_ID', env('CLOUDFLARE_ZONE_ID', 'ecd4115fa760df3dd0a5f9c0e2caee2d')),
        /**
         * Пул зон для новых поддоменов (случайный выбор). Не включайте vpn-telegram.com, если не хотите светить домен.
         * zone_id — из Cloudflare (обзор домена), domain — корневой домен зоны (для сопоставления имён записей).
         */
        'dns_zones' => array_values(array_filter([
            [
                'zone_id' => env('CLOUDFLARE_DNS_ZONE_KVN_FREE', '23ccc49cc28d56e1c2efb0e65f7592df'),
                'domain' => env('CLOUDFLARE_DNS_DOMAIN_KVN_FREE', 'kvnfreetest.uk'),
            ],
            [
                'zone_id' => env('CLOUDFLARE_DNS_ZONE_KVN_COCO', '14e5935e8704782977a1c330fa33795d'),
                'domain' => env('CLOUDFLARE_DNS_DOMAIN_KVN_COCO', 'kvncococ.org'),
            ],
        ], static function ($z) {
            return !empty($z['zone_id']) && !empty($z['domain']);
        })),
    ],

    'telegram' => [
        'client_id' => env('TELEGRAM_BOT_TOKEN'),  // Это будет ваш bot token (например: '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11')
        'client_secret' => env('TELEGRAM_BOT_NAME'), // Это username вашего бота (например: 'MyBot')
        // Должен совпадать с APP_CONFIG_PUBLIC_URL + /personal/auth/telegram/callback (см. UrlHelper::personalRoute)
        'redirect' => env('TELEGRAM_REDIRECT_URI'),
    ],

    's3_logs' => [
        'access_key' => env('S3_LOGS_ACCESS_KEY'),
        'secret_key' => env('S3_LOGS_SECRET_KEY'),
        'bucket' => env('S3_LOGS_BUCKET', 's3://logsvpn'),
    ],
];
