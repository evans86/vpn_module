<?php

namespace App\Services\VPN;

use App\Models\VPN\VpnDirectDomain;

/**
 * Профиль sing-box (SFA, явный ?format=sing-box): реальные outbounds из URI подписки + selector,
 * split-routing «без VPN» — inline rule_set + route.rules (sing-box 1.10+), плюс DNS.
 *
 * Графические клиенты (Hiddify) часто не применяют только «плоские» route.rules с domain_suffix;
 * inline rule_set с тем же списком суффиксов обрабатывается стабильнее.
 *
 * Обязательно правило action: sniff в начале route.rules: иначе TLS идёт по IP до SNI,
 * domain_suffix не матчится — как без sniffer в Clash (см. sing-box migration 1.11 / rule_action sniff).
 *
 * @see https://sing-box.sagernet.org/configuration/rule-set/
 * @see https://sing-box.sagernet.org/configuration/outbound/
 */
class SubscriptionSingBoxProfileBuilder
{
    private const DIRECT_RULE_SET_TAG = 'vpn-direct-domains';
    /**
     * @param  array<int, string>  $directDomains
     * @param  array<int, string>  $subscriptionUris  Строки vless/vmess/trojan/ss из той же подписки, что и plain text
     *
     * @throws \RuntimeException если не удалось собрать ни одного outbound
     */
    public function build(array $directDomains, array $subscriptionUris): string
    {
        $suffixes = $this->normalizeDomainSuffixes($directDomains);
        $dottedSuffixes = $this->toSingBoxDottedSuffixes($suffixes, $directDomains);

        $route = $this->buildRoute($dottedSuffixes, $directDomains);
        $dns = $this->buildDns($dottedSuffixes);

        $proxyOutbounds = [];
        $proxyTags = [];
        $i = 0;
        foreach ($subscriptionUris as $uri) {
            $uri = trim((string) $uri);
            if ($uri === '') {
                continue;
            }
            $tag = 'vpn-'.(++$i);
            $ob = $this->parseAnyUri($uri, $tag);
            if ($ob !== null) {
                $proxyOutbounds[] = $ob;
                $proxyTags[] = $tag;
            }
        }

        if ($proxyTags === []) {
            throw new \RuntimeException('sing-box: в подписке нет поддерживаемых URI (vless/vmess/trojan/ss)');
        }

        $outbounds = [
            [
                'type' => 'direct',
                'tag' => 'direct',
            ],
            ...$proxyOutbounds,
            [
                'type' => 'selector',
                'tag' => 'vpn-sub',
                'outbounds' => array_merge($proxyTags, ['direct']),
                'default' => $proxyTags[0],
            ],
        ];

        $config = [
            'log' => [
                'level' => 'info',
                'timestamp' => true,
            ],
            'dns' => $dns,
            'outbounds' => $outbounds,
            'route' => $route,
        ];

        try {
            return json_encode(
                $config,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException('sing-box profile JSON: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseAnyUri(string $uri, string $tag): ?array
    {
        $lower = strtolower($uri);
        if (str_starts_with($lower, 'vless://')) {
            return $this->parseVless($uri, $tag);
        }
        if (str_starts_with($lower, 'vmess://')) {
            return $this->parseVmess($uri, $tag);
        }
        if (str_starts_with($lower, 'trojan://')) {
            return $this->parseTrojan($uri, $tag);
        }
        if (str_starts_with($lower, 'ss://')) {
            return $this->parseShadowsocks($uri, $tag);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseVless(string $uri, string $tag): ?array
    {
        $p = parse_url($uri);
        if ($p === false || empty($p['host'])) {
            return null;
        }
        $uuid = isset($p['user']) ? rawurldecode((string) $p['user']) : '';
        if ($uuid === '') {
            return null;
        }
        $server = $p['host'];
        $port = (int) ($p['port'] ?? 443);
        parse_str($p['query'] ?? '', $q);

        $ob = [
            'type' => 'vless',
            'tag' => $tag,
            'server' => $server,
            'server_port' => $port,
            'uuid' => $uuid,
        ];

        $network = strtolower((string) ($q['type'] ?? $q['net'] ?? 'tcp'));
        $this->applyV2Transport($ob, $network, $q, $server);

        $sec = strtolower((string) ($q['security'] ?? 'none'));
        if ($sec === 'tls' || $sec === 'reality') {
            $tls = [
                'enabled' => true,
                'server_name' => (string) ($q['sni'] ?? $q['host'] ?? $server),
            ];
            if (! empty($q['fp'])) {
                $tls['utls'] = [
                    'enabled' => true,
                    'fingerprint' => (string) $q['fp'],
                ];
            }
            if ($sec === 'reality' && ! empty($q['pbk'])) {
                $tls['reality'] = [
                    'enabled' => true,
                    'public_key' => (string) $q['pbk'],
                    'short_id' => (string) ($q['sid'] ?? ''),
                ];
            }
            $ob['tls'] = $tls;
        }

        if (! empty($q['flow'])) {
            $ob['flow'] = (string) $q['flow'];
        }

        return $ob;
    }

    /**
     * @param  array<string, string>  $q
     * @param  array<string, mixed>  $ob
     */
    private function applyV2Transport(array &$ob, string $network, array $q, string $server): void
    {
        if ($network === 'tcp' || $network === '') {
            return;
        }

        if ($network === 'ws') {
            $ob['transport'] = [
                'type' => 'ws',
                'path' => (string) ($q['path'] ?? '/'),
                'headers' => [
                    'Host' => (string) ($q['host'] ?? $server),
                ],
            ];

            return;
        }

        if ($network === 'grpc') {
            $ob['transport'] = [
                'type' => 'grpc',
                'service_name' => (string) ($q['serviceName'] ?? $q['service'] ?? ''),
            ];

            return;
        }

        if ($network === 'http' || $network === 'h2') {
            $ob['transport'] = [
                'type' => 'http',
                'path' => (string) ($q['path'] ?? '/'),
                'host' => [(string) ($q['host'] ?? $server)],
            ];

            return;
        }

        if ($network === 'httpupgrade') {
            $ob['transport'] = [
                'type' => 'httpupgrade',
                'path' => (string) ($q['path'] ?? '/'),
                'host' => (string) ($q['host'] ?? $server),
            ];

            return;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseVmess(string $uri, string $tag): ?array
    {
        $payload = substr($uri, strlen('vmess://'));
        $hashPos = strpos($payload, '#');
        if ($hashPos !== false) {
            $payload = substr($payload, 0, $hashPos);
        }
        $json = base64_decode($payload, true);
        if ($json === false) {
            return null;
        }
        $cfg = json_decode($json, true);
        if (! is_array($cfg)) {
            return null;
        }

        $server = (string) ($cfg['add'] ?? '');
        $port = (int) ($cfg['port'] ?? 0);
        $uuid = (string) ($cfg['id'] ?? '');
        if ($server === '' || $port <= 0 || $uuid === '') {
            return null;
        }

        $ob = [
            'type' => 'vmess',
            'tag' => $tag,
            'server' => $server,
            'server_port' => $port,
            'uuid' => $uuid,
            'alter_id' => (int) ($cfg['aid'] ?? 0),
            'security' => 'auto',
        ];

        $net = strtolower((string) ($cfg['net'] ?? 'tcp'));
        if ($net === 'ws') {
            $ob['transport'] = [
                'type' => 'ws',
                'path' => (string) ($cfg['path'] ?? '/'),
                'headers' => [
                    'Host' => (string) ($cfg['host'] ?? $server),
                ],
            ];
        } elseif ($net === 'grpc') {
            $ob['transport'] = [
                'type' => 'grpc',
                'service_name' => (string) ($cfg['path'] ?? ''),
            ];
        } elseif ($net === 'h2' || $net === 'http') {
            $ob['transport'] = [
                'type' => 'http',
                'path' => (string) ($cfg['path'] ?? '/'),
                'host' => [(string) ($cfg['host'] ?? $server)],
            ];
        } elseif ($net !== 'tcp' && $net !== '') {
            return null;
        }

        $tlsFlag = $cfg['tls'] ?? '';
        if ($tlsFlag === 'tls' || $tlsFlag === true || $tlsFlag === '1') {
            $ob['tls'] = [
                'enabled' => true,
                'server_name' => (string) ($cfg['sni'] ?? $cfg['host'] ?? $server),
            ];
        }

        return $ob;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseTrojan(string $uri, string $tag): ?array
    {
        $p = parse_url($uri);
        if ($p === false || empty($p['host'])) {
            return null;
        }
        $password = isset($p['user']) ? rawurldecode((string) $p['user']) : '';
        if ($password === '') {
            return null;
        }
        $server = $p['host'];
        $port = (int) ($p['port'] ?? 443);
        parse_str($p['query'] ?? '', $q);

        $ob = [
            'type' => 'trojan',
            'tag' => $tag,
            'server' => $server,
            'server_port' => $port,
            'password' => $password,
        ];

        $network = strtolower((string) ($q['type'] ?? $q['net'] ?? 'tcp'));
        $this->applyV2Transport($ob, $network, $q, $server);

        $sec = strtolower((string) ($q['security'] ?? 'tls'));
        if ($sec !== 'none') {
            $ob['tls'] = [
                'enabled' => true,
                'server_name' => (string) ($q['sni'] ?? $q['host'] ?? $server),
            ];
            if (! empty($q['fp'])) {
                $ob['tls']['utls'] = [
                    'enabled' => true,
                    'fingerprint' => (string) $q['fp'],
                ];
            }
        }

        return $ob;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseShadowsocks(string $uri, string $tag): ?array
    {
        $rest = substr($uri, strlen('ss://'));
        $hashPos = strpos($rest, '#');
        if ($hashPos !== false) {
            $rest = substr($rest, 0, $hashPos);
        }
        if ($rest === '') {
            return null;
        }

        if (! str_contains($rest, '@')) {
            $decoded = base64_decode($rest, true);
            if ($decoded === false || ! str_contains($decoded, '@')) {
                return null;
            }
            $rest = $decoded;
        }

        $atPos = strrpos($rest, '@');
        if ($atPos === false) {
            return null;
        }
        $left = substr($rest, 0, $atPos);
        $right = substr($rest, $atPos + 1);

        $method = '';
        $password = '';
        $b64 = base64_decode($left, true);
        if ($b64 !== false && str_contains($b64, ':')) {
            [$method, $password] = explode(':', $b64, 2);
        } elseif (str_contains($left, ':')) {
            [$method, $password] = explode(':', $left, 2);
        } else {
            return null;
        }

        $method = trim($method);
        $password = trim($password);
        if ($method === '' || $password === '') {
            return null;
        }

        if (str_starts_with($right, '[')) {
            if (! preg_match('#^\[([^\]]+)\]:(\d+)$#', $right, $m)) {
                return null;
            }
            $host = $m[1];
            $port = (int) $m[2];
        } elseif (preg_match('#^([^:]+):(\d+)$#', $right, $m)) {
            $host = $m[1];
            $port = (int) $m[2];
        } else {
            return null;
        }

        return [
            'type' => 'shadowsocks',
            'tag' => $tag,
            'server' => $host,
            'server_port' => $port,
            'method' => $method,
            'password' => $password,
        ];
    }

    /**
     * Как в админке / VpnDirectDomain::normalizeDomain (URL → хост, .ru → ru).
     *
     * @param  array<int, string>  $directDomains
     * @return array<int, string>
     */
    private function normalizeDomainSuffixes(array $directDomains): array
    {
        $out = [];
        foreach ($directDomains as $d) {
            $n = VpnDirectDomain::normalizeDomain(trim((string) $d));
            if ($n === '') {
                continue;
            }
            if (str_starts_with($n, '*.')) {
                $s = substr($n, 2);
                if ($s !== '') {
                    $out[] = $s;
                }
            } else {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * sing-box ожидает domain_suffix с ведущей точкой (см. документацию); более длинные суффиксы — раньше.
     *
     * @param  array<int, string>  $suffixes
     * @param  array<int, string>  $directDomains
     * @return array<int, string>
     */
    private function toSingBoxDottedSuffixes(array $suffixes, array $directDomains): array
    {
        $dotted = [];
        foreach ($suffixes as $s) {
            $d = $this->singBoxDomainSuffix((string) $s);
            if ($d !== '') {
                $dotted[] = $d;
            }
        }
        if ($this->includesRuZone($directDomains)) {
            $dotted[] = '.xn--p1ai';
        }
        $dotted = array_values(array_unique($dotted));
        usort($dotted, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $dotted;
    }

    private function singBoxDomainSuffix(string $suffixWithoutLeadingDot): string
    {
        $suffixWithoutLeadingDot = trim($suffixWithoutLeadingDot);
        if ($suffixWithoutLeadingDot === '') {
            return '';
        }
        if (str_starts_with($suffixWithoutLeadingDot, '.')) {
            return $suffixWithoutLeadingDot;
        }

        return '.'.$suffixWithoutLeadingDot;
    }

    /**
     * DNS через direct для тех же суффиксов — иначе запросы к .ru резолвятся через VPN и маршрут/DIRECT ломается.
     *
     * @param  array<int, string>  $dottedSuffixes
     * @return array<string, mixed>
     */
    private function buildDns(array $dottedSuffixes): array
    {
        $servers = [
            [
                'type' => 'udp',
                'tag' => 'dns-direct',
                'server' => '77.88.8.8',
                'server_port' => 53,
                'detour' => 'direct',
            ],
            [
                'type' => 'udp',
                'tag' => 'dns-default',
                'server' => '8.8.8.8',
                'server_port' => 53,
                'detour' => 'vpn-sub',
            ],
        ];

        $rules = [];
        if ($dottedSuffixes !== []) {
            $rules[] = [
                'rule_set' => [self::DIRECT_RULE_SET_TAG],
                'action' => 'route',
                'server' => 'dns-direct',
            ];
        }

        return [
            'servers' => $servers,
            'rules' => $rules,
            'final' => 'dns-default',
            'strategy' => 'prefer_ipv4',
            'reverse_mapping' => true,
        ];
    }

    /**
     * @param  array<int, string>  $dottedSuffixes  Уже с ведущей точкой
     * @param  array<int, string>  $directDomains
     * @return array<string, mixed>
     */
    private function buildRoute(array $dottedSuffixes, array $directDomains): array
    {
        $ruleSets = [];
        if ($dottedSuffixes !== []) {
            $ruleSets[] = [
                'type' => 'inline',
                'tag' => self::DIRECT_RULE_SET_TAG,
                'rules' => [
                    [
                        'domain_suffix' => $dottedSuffixes,
                    ],
                ],
            ];
        }

        $rules = [
            [
                'action' => 'sniff',
                'timeout' => '1s',
            ],
        ];
        if ($dottedSuffixes !== []) {
            $rules[] = [
                'rule_set' => [self::DIRECT_RULE_SET_TAG],
                'action' => 'route',
                'outbound' => 'direct',
            ];
        }

        if ($this->includesRuZone($directDomains)) {
            $rules[] = [
                'geoip' => ['ru'],
                'action' => 'route',
                'outbound' => 'direct',
            ];
        }

        $rules[] = [
            'ip_is_private' => true,
            'action' => 'route',
            'outbound' => 'direct',
        ];

        $route = [
            'rules' => $rules,
            'final' => 'vpn-sub',
            'auto_detect_interface' => true,
            'override_android_vpn' => true,
            'default_domain_strategy' => 'prefer_ipv4',
        ];

        if ($ruleSets !== []) {
            $route['rule_set'] = $ruleSets;
        }

        return $route;
    }

    /**
     * @param  array<int, string>  $directDomains
     */
    private function includesRuZone(array $directDomains): bool
    {
        foreach ($directDomains as $d) {
            if (VpnDirectDomain::normalizeDomain((string) $d) === 'ru') {
                return true;
            }
        }

        return false;
    }
}
