<?php

$multiProviderParts = array_values(array_filter(array_map('trim', explode(',', (string) env('PANEL_MULTI_PROVIDER_SLOTS', ''))), function ($p) {
    return (string) $p !== '';
}));
$multiProviderAllowAll = false;
foreach ($multiProviderParts as $p) {
    $pLower = strtolower((string) $p);
    if ($p === '*' || $pLower === 'all') {
        $multiProviderAllowAll = true;
        break;
    }
}
$multiProviderSlotsFiltered = array_values(array_filter($multiProviderParts, function ($p) {
    $pLower = strtolower((string) $p);

    return $p !== '*' && $pLower !== 'all';
}));
if ($multiProviderAllowAll) {
    $multiProviderSlotsFiltered = [];
}
$multiProviderEnabled = $multiProviderAllowAll || $multiProviderSlotsFiltered !== [];

return [
    /*
    |--------------------------------------------------------------------------
    | Стратегия выбора панели для ротации (активация, модуль, мульти-слоты)
    |--------------------------------------------------------------------------
    |
    | simple (по умолчанию) — одна агрегация: число активных ключей на панели
    | (server_user + key_activate_user + key_activate.status = ACTIVE), один JOIN
    | GROUP BY panel_id. Без подзапросов к server_monitoring и без whereHas.
    |
    | intelligent — прежняя логика: свежая статистика Marzban + «активные» ключи
    | через связь key_activate_user + время последнего server_user + score.
    |
    */

    'selection_strategy' => strtolower((string) env('PANEL_SELECTION_STRATEGY', 'simple')),

    /*
    |--------------------------------------------------------------------------
    | Выбор панели v2 (scope в БД, см. panel:recalculate-selection-scope)
    |--------------------------------------------------------------------------
    |
    | При включении игнорируется simple/intelligent: порядок по selection_scope_score.
    | PANEL_SELECTION_V2_CACHE_TTL=0 — без кэша выбора (актуальный score при каждой активации).
    |
    */

    'selection_v2_enabled' => filter_var(env('PANEL_SELECTION_V2', false), FILTER_VALIDATE_BOOLEAN),

    'selection_v2_cache_ttl' => (int) env('PANEL_SELECTION_V2_CACHE_TTL', 0),

    /*
    |--------------------------------------------------------------------------
    | Тариф для фильтра серверов при активации (пока одно значение из server.tariff_tier)
    |--------------------------------------------------------------------------
    */

    'activation_tariff_tier' => strtolower((string) env('PANEL_ACTIVATION_TARIFF_TIER', 'full')),

    'scope_recalc_enabled' => filter_var(env('PANEL_SCOPE_RECALC_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Кэш результата выбора панели
    |--------------------------------------------------------------------------
    */

    'selection_cache_ttl' => (int) env('PANEL_SELECTION_CACHE_TTL', 90),

    /*
    |--------------------------------------------------------------------------
    | Прогрев кэша выбора панели (cron + `php artisan panel:warm-selection-cache`)
    |--------------------------------------------------------------------------
    |
    | Пока кэш живёт, активация и покупка попадают в уже посчитанный выбор.
    | Рекомендуется: `selection_cache_ttl` не меньше интервала прогрева в секундах
    | (например TTL 90 с и прогрев каждую минуту).
    |
    */

    'selection_warm_enabled' => filter_var(env('PANEL_SELECTION_WARM_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'selection_warm_every_minutes' => max(1, min(30, (int) env('PANEL_SELECTION_WARM_EVERY_MINUTES', 1))),

    /*
    |--------------------------------------------------------------------------
    | Кэш страницы «Настройки распределения» (админка)
    |--------------------------------------------------------------------------
    |
    | Сборка данных — тяжёлая (SQL по многим панелям). В HTTP-запросе только чтение кэша;
    | прогрев: `php artisan panel:warm-rotation-settings` и cron (см. Console\\Kernel).
    |
    */

    'rotation_settings_cache_key' => 'panel_rotation_settings_comparison_v2',

    'rotation_settings_cache_ttl' => max(60, (int) env('PANEL_ROTATION_SETTINGS_CACHE_TTL', 900)),

    /*
    | Сколько панелей попадает в кэш panel:warm-rotation-settings (раньше было 150 — у больших id
    | не было строки Marzban на странице «Панели и распределение»).
    */
    'rotation_comparison_panels_limit' => max(50, min(5000, (int) env('PANEL_ROTATION_COMPARISON_PANELS_LIMIT', 2000))),

    /*
    |--------------------------------------------------------------------------
    | Кэш страницы «Панели и распределение» (снимок трафика API только из кэша, без HTTP)
    |--------------------------------------------------------------------------
    |
    | Раньше вызывался getMonthlyStatistics() на каждый запрос — цикл по всем панелям
    | и возможные запросы к API Timeweb. Сейчас: только панели в ротации + только кэш.
    |
    */

    'panel_distribution_page_cache_ttl' => max(60, (int) env('PANEL_DISTRIBUTION_PAGE_CACHE_TTL', 600)),

    'rotation_settings_warm_enabled' => filter_var(env('PANEL_ROTATION_SETTINGS_WARM_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'rotation_settings_warm_every_minutes' => max(1, min(59, (int) env('PANEL_ROTATION_SETTINGS_WARM_EVERY_MINUTES', 5))),

    /*
    |--------------------------------------------------------------------------
    | Server Traffic Limit
    |--------------------------------------------------------------------------
    |
    | Лимит трафика для серверов в байтах (по умолчанию 32TB)
    |
    */

    'server_traffic_limit' => env('SERVER_TRAFFIC_LIMIT', 32 * 1024 * 1024 * 1024 * 1024), // 32 TB

    /*
    |--------------------------------------------------------------------------
    | Traffic Cache TTL
    |--------------------------------------------------------------------------
    |
    | Время кэширования данных о трафике серверов в секундах
    |
    */

    'traffic_cache_ttl' => env('TRAFFIC_CACHE_TTL', 1800), // 30 минут

    /*
    |--------------------------------------------------------------------------
    | Maximum Connections Per User
    |--------------------------------------------------------------------------
    |
    | Максимальное количество одновременно подключаемых устройств для одного пользователя
    |
    */

    'max_connections' => env('MAX_CONNECTIONS', 4),

    /*
    |--------------------------------------------------------------------------
    | Excluded Locations
    |--------------------------------------------------------------------------
    |
    | Список ID локаций, которые нужно исключить из выбора панели
    | Например, если определенные страны не поддерживаются некоторыми сервисами
    | (например, Gemini), их можно исключить из автоматического выбора
    |
    | Формат: массив ID локаций или строка с ID через запятую
    | Пример: [1, 2] или '1,2'
    |
    */

    'excluded_locations' => env('EXCLUDED_LOCATIONS', '') ? explode(',', env('EXCLUDED_LOCATIONS')) : [],

    /*
    |--------------------------------------------------------------------------
    | Excluded Server IPs
    |--------------------------------------------------------------------------
    |
    | Список IP-адресов серверов, которые нужно исключить из выбора панели
    | Например, если определенные IP-адреса заблокированы сервисами
    | (например, Gemini), их можно исключить из автоматического выбора
    |
    | Формат: массив IP-адресов или строка с IP через запятую
    | Пример: ['91.84.125.62', '192.168.1.1'] или '91.84.125.62,192.168.1.1'
    |
    */

    'excluded_server_ips' => env('EXCLUDED_SERVER_IPS', '') ? explode(',', env('EXCLUDED_SERVER_IPS')) : [],

    /*
    |--------------------------------------------------------------------------
    | Excluded Server IDs
    |--------------------------------------------------------------------------
    |
    | Список ID серверов, которые нужно исключить из выбора панели
    | Например, если определенные серверы имеют проблемы или заблокированы
    |
    | Формат: массив ID серверов или строка с ID через запятую
    | Пример: [1, 2, 3] или '1,2,3'
    |
    */

    'excluded_server_ids' => env('EXCLUDED_SERVER_IDS', '') ? explode(',', env('EXCLUDED_SERVER_IDS')) : [],

    /*
    |--------------------------------------------------------------------------
    | Multi-Provider Slots (резервирование по провайдерам)
    |--------------------------------------------------------------------------
    |
    | Список провайдеров (server.provider), по одному слоту на каждый.
    | При активации ключа создаётся пользователь на одной панели каждого провайдера
    | (выбор панели внутри провайдера — интеллектуальный, см. PanelRepository);
    | итоговая подписка объединяет конфиги со всех слотов — при падении одного
    | сервера клиент может переключиться на другой провайдер.
    |
    | Пустой = старый режим (один провайдер на ключ). Чтобы включить: в .env задать
    | PANEL_MULTI_PROVIDER_SLOTS=vdsina,timeweb (через запятую, без пробелов или с пробелами).
    | Специальное значение * или all — все провайдеры из пула ротации для данного тарифа (tier),
    | до max_provider_slots слотов без повторения провайдера; порядок по selection_scope_score (см. greedy).
    | Для ручных серверов указывайте латинский код провайдера (server.provider), тот же,
    | что получается из поля «Название провайдера» при добавлении сервера вручную.
    |
    | max_provider_slots — максимум слотов на ключ (по умолчанию 3). Участвуют до N лучших панелей
    | по score с разными провайдерами (не «первые N строк» из .env при жадном режиме).
    |
    */

    'multi_provider_slots' => $multiProviderSlotsFiltered,

    'multi_provider_allow_all' => $multiProviderAllowAll,

    'multi_provider_enabled' => $multiProviderEnabled,

    'max_provider_slots' => (int) env('PANEL_MAX_PROVIDER_SLOTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Мульти-провайдер: жадный выбор по selection_scope_score (только при PANEL_SELECTION_V2=true)
    |--------------------------------------------------------------------------
    |
    | Если true — среди кандидатов с нужным tariff_tier берутся лучшие панели по score без повторения
    | провайдера (до max_provider_slots). Порядок слотов — по убыванию score (не порядок в .env).
    | Режим * / all требует greedy + v2; иначе используется одиночная панель.
    | Если false — для каждого провайдера из списка выбирается лучшая панель; порядок слотов как в .env.
    |
    */

    'multi_provider_greedy' => filter_var(env('PANEL_MULTI_PROVIDER_GREEDY', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Прогрев конфига после активации (HTTP к vpn.config.refresh)
    |--------------------------------------------------------------------------
    | defer_warm_config_after_activation — true: не ждать прогрев внутри activate()
    | (запуск после отправки ответа Laravel, ускоряет ответ Telegram без Redis).
    | skip_warm_config_after_activation — полностью отключить прогрев (конфиг подтянется при первом открытии).
    */

    'defer_warm_config_after_activation' => env('KEY_DEFER_WARM_CONFIG_AFTER_ACTIVATION', true),

    'skip_warm_config_after_activation' => env('KEY_ACTIVATE_SKIP_WARM_CONFIG', false),

    'warm_config_http_timeout' => (int) env('KEY_WARM_CONFIG_HTTP_TIMEOUT', 45),

    /*
    |--------------------------------------------------------------------------
    | Кнопка «Обновить» на странице конфига (/config/{token}/refresh)
    |--------------------------------------------------------------------------
    | Синхронный запрос к Marzban: при нескольких панелях время растёт. Увеличьте
    | vpn_config_refresh_time_limit и proxy_read_timeout / fastcgi_read_timeout в nginx.
    */
    'vpn_config_refresh_time_limit' => (int) env('VPN_CONFIG_REFRESH_TIME_LIMIT', 300),

    /*
    |--------------------------------------------------------------------------
    | WARP на ноде (локальный SOCKS): узкий маршрут = только Gemini (config/vpn.php — gemini_warp_*), не весь Google.
    |--------------------------------------------------------------------------
    | Порт по умолчанию, если в карточке панели не указан warp_socks_port.
    | Установка Marzban в проекте — через Docker; Xray в контейнере должен стучаться в SOCKS
    | на хосте (часто шлюз bridge 172.17.0.1, не 127.0.0.1). sing-box: listen 0.0.0.0:port.
    | См. PANEL_WARP_DEFAULT_SOCKS_HOST; WARP=127.0.0.1 только если Marzban без изоляции Docker.
    |
    | Доп. маршруты (только при выключенном «все сайты через WARP»): списки через запятую.
    | geosite: имена в нижнем регистре, как в geosite.dat на ноде (v2ray-domain-list-community / Loyalsoldier).
    | domain: можно без префикса — будет domain:…
    | Плюс PANEL_WARP_ROUTING_*_EXTRA. Базовый набор — gemini_warp_* в config/vpn.php.
    */
    'warp_default_socks_port' => (int) env('PANEL_WARP_DEFAULT_SOCKS_PORT', 40000),

    'warp_default_socks_host' => trim((string) env('PANEL_WARP_DEFAULT_SOCKS_HOST', '172.17.0.1')) ?: '172.17.0.1',
    'warp_routing_geosite_extra' => array_values(
        array_filter(
            array_map('trim', explode(',', (string) env('PANEL_WARP_ROUTING_GEOSITE_EXTRA', '')))
        )
    ),
    'warp_routing_domain_extra' => array_values(
        array_filter(
            array_map('trim', explode(',', (string) env('PANEL_WARP_ROUTING_DOMAIN_EXTRA', '')))
        )
    ),

    // Документация: фактическое значение по умолчанию для новых строк в БД — в миграции warp_routing_all
    'warp_routing_all_default' => filter_var(
        env('PANEL_WARP_DEFAULT_ROUTING_ALL', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Исходящий WARP: socks (как в Marzban вар.2 — к локальному sing-box) или wireguard (Marzban вар.1 — в Xray).
    | @see https://marzban-docs.sm1ky.com/tutorials/cloudflare-warp/
    */
    // socks | wireguard | auto — auto: нативный WG из снимка на панели или .env, иначе SOCKS
    'warp_outbound_protocol' => strtolower((string) env('PANEL_WARP_OUTBOUND_PROTOCOL', 'auto')),

    /*
    | Нативный WireGuard в Xray: зашифрованный снимок с ноды (wgcf) в панели или PANEL_WARP_WG_* в .env.
    | Для protocol=wireguard без снимка нужны PANEL_WARP_WG_SECRET_KEY + PANEL_WARP_WG_ADDRESS.
    | В Docker/LXC обычно no_kernel_tun=true.
    */
    'warp_wireguard_secret_key' => trim((string) env('PANEL_WARP_WG_SECRET_KEY', '')),
    'warp_wireguard_address' => array_values(
        array_filter(
            array_map('trim', explode(',', (string) env('PANEL_WARP_WG_ADDRESS', '')))
        )
    ),
    'warp_wireguard_peer_public_key' => trim((string) env(
        'PANEL_WARP_WG_PEER_PUBLIC_KEY',
        'bmXOC+F1FxEMF9dyiK2H5/1SUtzH0JuVo51h2wPfgyo='
    )),
    'warp_wireguard_endpoint' => trim((string) env('PANEL_WARP_WG_ENDPOINT', 'engage.cloudflareclient.com:2408'))
        ?: 'engage.cloudflareclient.com:2408',
    // Три байта через запятую, как в wgcf (или пусто — не передаётся в Xray)
    'warp_wireguard_reserved' => (static function (): array {
        $raw = trim((string) env('PANEL_WARP_WG_RESERVED', ''));
        if ($raw === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $raw));
        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || ! is_numeric($p)) {
                continue;
            }
            $out[] = (int) $p;
        }

        return count($out) === 3 ? $out : [];
    })(),
    'warp_wireguard_no_kernel_tun' => filter_var(
        env('PANEL_WARP_WG_NO_KERNEL_TUN', true),
        FILTER_VALIDATE_BOOL
    ),
    'warp_wireguard_mtu' => (int) env('PANEL_WARP_WG_MTU', 1420),

    /*
    | Узкий маршрут: marzban — geosite:google + geosite:openai (как в доке), gemini — только список config/vpn.php.
    */
    'warp_selective_base' => strtolower((string) env('PANEL_WARP_SELECTIVE_BASE', 'marzban')),

    /*
    | При «все сайты через WARP»: исключения DIRECT **до** правила 0.0.0.0/0 → WARP.
    | Иначе часть направлений даёт таймаут в sing-box (no route / недоступен DC Telegram и т.д.).
    */
    'warp_routing_full_bypass_geosite' => array_values(
        array_filter(
            array_map('trim', explode(',', (string) env('PANEL_WARP_FULL_BYPASS_GEOSITE', 'geosite:telegram')))
        )
    ),
    'warp_routing_full_bypass_ip_cidr' => array_values(
        array_filter(
            array_map('trim', explode(',', (string) env('PANEL_WARP_FULL_BYPASS_IP', '149.154.0.0/16,91.108.0.0/16')))
        )
    ),
];


