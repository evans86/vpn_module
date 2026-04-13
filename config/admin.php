<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Basic для префикса /admin (дополнительно к входу в Laravel)
    |--------------------------------------------------------------------------
    |
    | Если заданы оба значения, перед сессией Laravel запрашивается Basic Auth.
    | Пароль в .env в открытом виде — храните .env вне веб-корня и с правами 600.
    |
    */
    'http_basic_user' => env('ADMIN_HTTP_BASIC_USER'),
    'http_basic_password' => env('ADMIN_HTTP_BASIC_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Уведомления в Telegram после успешного HTTP Basic (опционально)
    |--------------------------------------------------------------------------
    |
    | Нужны оба значения: токен бота от @BotFather и ваш chat_id (напишите боту /start,
    | затем https://api.telegram.org/bot<TOKEN>/getUpdates).
    |
    */
    'http_basic_notify_telegram_token' => env('ADMIN_HTTP_BASIC_NOTIFY_TELEGRAM_TOKEN'),
    'http_basic_notify_telegram_chat_id' => env('ADMIN_HTTP_BASIC_NOTIFY_TELEGRAM_CHAT_ID'),

];
