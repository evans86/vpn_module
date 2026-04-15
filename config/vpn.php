<?php

return [
    'external_server_url' => env('VPN_EXTERNAL_SERVER_URL', 'https://vpnserver1733176779nl.bot-t.ru.vpn-telegram.com:61679'),

    /*
    |--------------------------------------------------------------------------
    | Кэш публичного JSON со списком доменов «без VPN» (/vpn/routing/direct-domains.json)
    |--------------------------------------------------------------------------
    */
    'direct_domains_cache_seconds' => (int) env('VPN_DIRECT_DOMAINS_CACHE_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | Подсказки в plain text подписке (строки с #) про split-routing
    |--------------------------------------------------------------------------
    | Не применяется к ?format=raw — иначе ломается загрузка узлов в Clash proxy-providers.
    */
    'subscription_direct_routing_hints' => filter_var(
        env('VPN_SUBSCRIPTION_DIRECT_ROUTING_HINTS', true),
        FILTER_VALIDATE_BOOL
    ),
];
