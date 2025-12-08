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
    ],

    'cloudflare' => [
        'email' => env('CLOUDFLARE_EMAIL', 'support@bot-t.ru'),
        'api_key' => env('CLOUDFLARE_API_KEY', '1697f393d7d2fceb7866b0c7062d025b8cfe6'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID', 'ecd4115fa760df3dd0a5f9c0e2caee2d'),
    ],

    'telegram' => [
        'client_id' => env('TELEGRAM_BOT_TOKEN'),  // Это будет ваш bot token (например: '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11')
        'client_secret' => env('TELEGRAM_BOT_NAME'), // Это username вашего бота (например: 'MyBot')
        'redirect' => env('TELEGRAM_REDIRECT_URI'), // URL callback (например: 'https://yourdomain.com/personal/auth/telegram/callback')
    ],

    's3_logs' => [
        'access_key' => env('S3_LOGS_ACCESS_KEY'),
        'secret_key' => env('S3_LOGS_SECRET_KEY'),
        'bucket' => env('S3_LOGS_BUCKET', 's3://logsvpn'),
    ],
];
