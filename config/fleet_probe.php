<?php

/**
 * Сводная проверка флота (ServerFleetProbeService): доп. «пинг» с хоста Laravel
 * до панелей и до ваших доменов (ICMP при наличии, плюс HTTPS-задержка).
 *
 * @see .env.example — FLEET_PROBE_EXTERNAL_APP_DOMAINS, FLEET_PROBE_SERVER_ZONE_DOMAINS,
 *      FLEET_PROBE_SERVER_ZONE_HTTPS_HOSTS, FLEET_PROBE_PANEL_HOSTS, FLEET_PROBE_OUR_DOMAINS
 */

$split = static function (?string $csv): array {
    if ($csv === null || trim($csv) === '') {
        return [];
    }
    $parts = preg_split('/\s*,\s*/', trim($csv), -1, PREG_SPLIT_NO_EMPTY);

    return array_values(array_unique(array_map('trim', is_array($parts) ? $parts : [])));
};

$externalAppProbeDomains = $split(env('FLEET_PROBE_EXTERNAL_APP_DOMAINS', 'cursevagrus.ru'));
$serverZoneProbeDomains = $split(env('FLEET_PROBE_SERVER_ZONE_DOMAINS', 'kvnfreetest.uk,kvncococ.org'));

// Зоны без A/AAAA на апексе (только wildcard для поддоменов): задайте FLEET_PROBE_SERVER_ZONE_HTTPS_HOSTS.
$serverZoneHttpsProbeHosts = $split(trim((string) env('FLEET_PROBE_SERVER_ZONE_HTTPS_HOSTS', '')));
$serverZoneTargetsForFleetProbe = $serverZoneHttpsProbeHosts !== []
    ? $serverZoneHttpsProbeHosts
    : $serverZoneProbeDomains;

$alwaysProbeOurDomains = array_values(array_unique(array_merge(
    $externalAppProbeDomains,
    $serverZoneTargetsForFleetProbe,
)));

return [

    /*
    | Полные URL или только хостнейм (будет добавлен https:// если нет схемы).
    | Примеры: vpn-telegram.com/admin,https://second-panel.example.com/
    */
    'panel_hosts' => $split(env('FLEET_PROBE_PANEL_HOSTS')),

    /*
    | Дополнительно подмешать URL всех настроенных панелей из таблицы panel (panel_adress).
    */
    'merge_panels_from_db' => filter_var(env('FLEET_PROBE_MERGE_PANELS_FROM_DB', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Публичные домены проекта (лендинг, конфиг-зеркало, бот-хост и т.д.)
    */
    'our_domains' => $split(env('FLEET_PROBE_OUR_DOMAINS')),

    /*
    | Хост(ы) внешнего веб-приложения — FLEET_PROBE_EXTERNAL_APP_DOMAINS. Пустое в .env — не проверять.
    */
    'external_app_probe_domains' => $externalAppProbeDomains,

    /*
    | Базовые домены зон серверов — FLEET_PROBE_SERVER_ZONE_DOMAINS. Для HTTPS по умолчанию проверяется апекс;
    | если у апекса нет DNS, задайте FLEET_PROBE_SERVER_ZONE_HTTPS_HOSTS (поддомены вместо корня).
    */
    'server_zone_probe_domains' => $serverZoneProbeDomains,

    /*
    | Явные хосты для HTTPS/ICMP по зонам серверов. Непустое значение заменяет апексы из server_zone_probe_domains.
    */
    'server_zone_https_probe_hosts' => $serverZoneHttpsProbeHosts,

    /*
    | Слияние: внешнее приложение + цели для зон (HTTPS_HOSTS либо апексы).
    */
    'always_probe_our_domains' => $alwaysProbeOurDomains,

    /*
    | Автоматически добавить хосты из APP_URL, APP_CONFIG_PUBLIC_URL и APP_MIRROR_URLS.
    */
    'merge_app_domain_hosts' => filter_var(env('FLEET_PROBE_MERGE_APP_DOMAIN_HOSTS', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Пытаться ICMP (ping -c 1) там, где доступно (Linux/macOS); иначе только HTTPS.
    */
    'prefer_icmp' => filter_var(env('FLEET_PROBE_PREFER_ICMP', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Таймауты для HTTPS-проверки (сек), отдельно от Http:: в сервисе.
    */
    'https_timeout' => max(3, min(30, (int) env('FLEET_PROBE_HTTPS_TIMEOUT', 12))),
    'https_connect_timeout' => max(2, min(15, (int) env('FLEET_PROBE_HTTPS_CONNECT_TIMEOUT', 5))),
];
