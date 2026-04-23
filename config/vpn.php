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

    /*
    |--------------------------------------------------------------------------
    | Профиль sing-box (JSON) для подписки: явный ?format=sing-box или UA sing-box (не Hiddify/Karing).
    |--------------------------------------------------------------------------
    | Узлы разбираются из URI подписки (vless/vmess/trojan/ss) + selector + route (DIRECT по списку из админки).
    | Отключите, если не нужен: VPN_SING_BOX_SUBSCRIPTION_PROFILE=false
    */
    'sing_box_subscription_profile' => filter_var(
        env('VPN_SING_BOX_SUBSCRIPTION_PROFILE', true),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | Gemini / Google AI: маршрут через Cloudflare WARP в JSON sing-box
    |--------------------------------------------------------------------------
    | Раз в .env задаётся WireGuard к engage.cloudflareclient.com; в подписку добавляется outbound cf-warp
    | и правила маршрутизации (пользователь только обновляет подписку в sing-box клиенте).
    | Без валидных ключей/файла профиль собирается без WARP (в лог — предупреждение).
    */
    'sing_box_warp_gemini' => filter_var(
        env('VPN_SING_BOX_WARP_GEMINI', false),
        FILTER_VALIDATE_BOOL
    ),
    'sing_box_warp_outbound_json_path' => env('VPN_SING_BOX_WARP_OUTBOUND_JSON_PATH', ''),
    'sing_box_warp_private_key' => env('VPN_SING_BOX_WARP_PRIVATE_KEY', ''),
    'sing_box_warp_local_addresses' => env('VPN_SING_BOX_WARP_LOCAL_ADDRESSES', ''),
    'sing_box_warp_reserved' => env('VPN_SING_BOX_WARP_RESERVED', ''),
    'sing_box_warp_server' => env('VPN_SING_BOX_WARP_SERVER', 'engage.cloudflareclient.com'),
    'sing_box_warp_server_port' => (int) env('VPN_SING_BOX_WARP_SERVER_PORT', 2408),
    'sing_box_warp_peer_public_key' => env(
        'VPN_SING_BOX_WARP_PEER_PUBLIC_KEY',
        'bmXOC+F1FxEMF9dyiK2H5/1SUtzH0JuVo51h2wPfgyo='
    ),
];
