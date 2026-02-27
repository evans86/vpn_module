<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram: логи миграции (основной и резервный бот)
    |--------------------------------------------------------------------------
    | Сообщения о ходе миграции на мульти-провайдер отправляются в оба бота.
    | Токены и chat_id задаются в .env. Chat_id: написать боту /start, затем
    | https://api.telegram.org/bot<TOKEN>/getUpdates — взять result[].message.chat.id
    */
    'bot' => [
        'token' => env('TELEGRAM_LOG_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_LOG_CHAT_ID'),
    ],
    'bot_2' => [
        'token' => env('TELEGRAM_LOG_BOT_2_TOKEN'),
        'chat_id' => env('TELEGRAM_LOG_CHAT_ID_2'),
    ],
];
