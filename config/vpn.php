<?php

return [
    'external_server_url' => env('VPN_EXTERNAL_SERVER_URL', 'https://vpnserver1733176779nl.bot-t.ru.vpn-telegram.com:61679'),

    /*
    |--------------------------------------------------------------------------
    | Трассировка времени загрузки страницы конфига (/config/{token}, /content, /refresh)
    |--------------------------------------------------------------------------
    | VPN_CONFIG_TRACE=true — включить запись в storage/logs/config-trace.log
    | VPN_CONFIG_TRACE_TOKENS — через запятую UUID ключей; пусто = все ключи (шумно на проде)
    */
    'config_trace' => [
        'enabled' => (bool) env('VPN_CONFIG_TRACE', false),
        'tokens' => array_values(array_filter(array_map('trim', explode(',', (string) env('VPN_CONFIG_TRACE_TOKENS', ''))))),
    ],
];
