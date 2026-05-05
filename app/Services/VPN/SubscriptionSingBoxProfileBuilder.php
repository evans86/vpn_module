<?php

namespace App\Services\VPN;

use App\Models\VPN\VpnDirectDomain;
use Illuminate\Support\Facades\Log;

/**
 * Профиль sing-box (SFA, явный ?format=sing-box): реальные outbounds из URI подписки + selector,
 * split-routing «без VPN» — inline rule_set + route.rules (sing-box 1.10+), плюс DNS.
 *
 * Опционально: Cloudflare WARP (WireGuard) + маршруты для Gemini / Google API — задаётся оператором в .env.
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

    private const GEMINI_WARP_RULE_SET_TAG = 'gemini-warp-routes';

    private const WARP_OUTBOUND_TAG = 'cf-warp';

    /**
     * @return array<int, array<string, mixed>>
     */
    private function geminiWarpRuleSetEntries(): array
    {
        $hosts = config('vpn.gemini_warp_full_hosts', []);
        $hosts = is_array($hosts) ? $hosts : [];
        $domain = [];
        foreach ($hosts as $h) {
            $h = is_string($h) ? trim($h) : '';
            if ($h !== '') {
                $domain[] = $h;
            }
        }

        $suffs = config('vpn.gemini_warp_domain_suffixes', []);
        $suffs = is_array($suffs) ? $suffs : [];
        $domainSuffix = [];
        foreach ($suffs as $s) {
            $s = is_string($s) ? trim($s) : '';
            if ($s === '') {
                continue;
            }
            $domainSuffix[] = '.'.ltrim($s, '.');
        }
        $domainSuffix = array_values(array_unique($domainSuffix));

        $rules = [];
        if ($domain !== []) {
            $rules[] = ['domain' => $domain];
        }
        if ($domainSuffix !== []) {
            $rules[] = ['domain_suffix' => $domainSuffix];
        }

        return $rules;
    }
    /**
     * @param  array<int, string>  $directDomains
     * @param  array<int, string>  $subscriptionUris  Строки vless/vmess/ss из той же подписки, что и plain text
     *
     * @throws \RuntimeException если не удалось собрать ни одного outbound
     */
    public function build(array $directDomains, array $subscriptionUris): string
    {
        $suffixes = $this->normalizeDomainSuffixes($directDomains);
        $dottedSuffixes = $this->toSingBoxDottedSuffixes($suffixes, $directDomains);

        $warpOutbound = null;
        if (config('vpn.sing_box_warp_gemini', false)) {
            $warpOutbound = $this->resolveCloudflareWarpOutbound();
            if ($warpOutbound === null) {
                Log::warning('sing-box: VPN_SING_BOX_WARP_GEMINI=true, но WARP outbound не собран — проверьте VPN_SING_BOX_WARP_OUTBOUND_JSON_PATH или PRIVATE_KEY+LOCAL_ADDRESSES');
            }
        }

        $useGeminiWarp = $warpOutbound !== null;
        $route = $this->buildRoute($dottedSuffixes, $directDomains, $useGeminiWarp);
        $dns = $this->buildDns($dottedSuffixes, $useGeminiWarp);

        $proxyOutbounds = [];
        $proxyTags = [];
        $usedTags = [];
        $fallbackSeq = 0;
        foreach ($subscriptionUris as $uri) {
            $uri = trim((string) $uri);
            if ($uri === '') {
                continue;
            }
            ++$fallbackSeq;
            $tag = $this->outboundTagFromSubscriptionUri($uri, $fallbackSeq, $usedTags);
            $ob = $this->parseAnyUri($uri, $tag);
            if ($ob !== null) {
                $proxyOutbounds[] = $ob;
                $proxyTags[] = $tag;
            }
        }

        if ($proxyTags === []) {
            throw new \RuntimeException('sing-box: в подписке нет поддерживаемых URI (vless/vmess/ss)');
        }

        $outbounds = [
            [
                'type' => 'direct',
                'tag' => 'direct',
            ],
        ];
        if ($warpOutbound !== null) {
            $outbounds[] = $warpOutbound;
        }
        $outbounds = array_merge(
            $outbounds,
            $proxyOutbounds,
            [
                [
                    'type' => 'selector',
                    'tag' => 'vpn-sub',
                    'outbounds' => array_merge($proxyTags, ['direct']),
                    'default' => $proxyTags[0],
                ],
            ]
        );

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
     * Имя узла из фрагмента URI подписки (# после ссылки), как в Marzban/Clash — иначе в UI только «vpn-1».
     *
     * @param  array<string, true>  $usedTags
     */
    private function outboundTagFromSubscriptionUri(string $uri, int $fallbackIndex, array &$usedTags): string
    {
        $fragment = parse_url($uri, PHP_URL_FRAGMENT);
        $name = '';
        if (is_string($fragment) && $fragment !== '') {
            $name = trim(rawurldecode(str_replace('+', ' ', $fragment)));
            $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        }
        if ($name === '' && str_starts_with(strtolower($uri), 'vmess://')) {
            $name = $this->vmessPsFromUri($uri);
        }

        if ($name === '') {
            $name = 'vpn-'.$fallbackIndex;
        } else {
            $name = $this->sanitizeOutboundTag($name, $fallbackIndex);
        }

        $base = $name;
        $n = 1;
        while (isset($usedTags[$name])) {
            $n++;
            $name = $base.'-'.$n;
        }
        $usedTags[$name] = true;

        return $name;
    }

    private function vmessPsFromUri(string $uri): string
    {
        $payload = substr($uri, strlen('vmess://'));
        $hashPos = strpos($payload, '#');
        if ($hashPos !== false) {
            $payload = substr($payload, 0, $hashPos);
        }
        $json = base64_decode($payload, true);
        if ($json === false) {
            return '';
        }
        $cfg = json_decode($json, true);

        return is_array($cfg) ? trim((string) ($cfg['ps'] ?? '')) : '';
    }

    private function sanitizeOutboundTag(string $name, int $fallbackIndex): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);
        if ($name === '') {
            return 'vpn-'.$fallbackIndex;
        }
        if (mb_strlen($name) > 96) {
            $name = mb_substr($name, 0, 93).'...';
        }

        return $name;
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
    private function buildDns(array $dottedSuffixes, bool $useGeminiWarp): array
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
        if ($useGeminiWarp) {
            $servers[] = [
                'type' => 'udp',
                'tag' => 'dns-gemini-warp',
                'server' => '1.1.1.1',
                'server_port' => 53,
                'detour' => self::WARP_OUTBOUND_TAG,
            ];
        }

        $rules = [];
        if ($dottedSuffixes !== []) {
            $rules[] = [
                'rule_set' => [self::DIRECT_RULE_SET_TAG],
                'action' => 'route',
                'server' => 'dns-direct',
            ];
        }
        if ($useGeminiWarp) {
            $rules[] = [
                'rule_set' => [self::GEMINI_WARP_RULE_SET_TAG],
                'action' => 'route',
                'server' => 'dns-gemini-warp',
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
    private function buildRoute(array $dottedSuffixes, array $directDomains, bool $useGeminiWarp): array
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
        if ($useGeminiWarp) {
            $ruleSets[] = [
                'type' => 'inline',
                'tag' => self::GEMINI_WARP_RULE_SET_TAG,
                'rules' => $this->geminiWarpRuleSetEntries(),
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
        if ($useGeminiWarp) {
            $rules[] = [
                'rule_set' => [self::GEMINI_WARP_RULE_SET_TAG],
                'action' => 'route',
                'outbound' => self::WARP_OUTBOUND_TAG,
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

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCloudflareWarpOutbound(): ?array
    {
        $fromFile = $this->loadWarpOutboundFromJsonPath();
        if ($fromFile !== null) {
            return $fromFile;
        }

        return $this->buildWarpOutboundFromEnvParts();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadWarpOutboundFromJsonPath(): ?array
    {
        $rawPath = trim((string) config('vpn.sing_box_warp_outbound_json_path', ''));
        if ($rawPath === '') {
            return null;
        }
        $path = $this->isAbsoluteFilesystemPath($rawPath) ? $rawPath : base_path($rawPath);
        if (! is_readable($path)) {
            Log::warning('sing-box: файл WARP JSON не читается', ['path' => $path]);

            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        try {
            $ob = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning('sing-box: WARP JSON невалиден', ['path' => $path]);

            return null;
        }
        if (! is_array($ob) || ($ob['type'] ?? '') !== 'wireguard') {
            Log::warning('sing-box: WARP JSON должен быть одним outbound-объектом с "type": "wireguard"');

            return null;
        }
        $ob['tag'] = self::WARP_OUTBOUND_TAG;

        return $ob;
    }

    private function isAbsoluteFilesystemPath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildWarpOutboundFromEnvParts(): ?array
    {
        $pk = trim((string) config('vpn.sing_box_warp_private_key', ''));
        $addrRaw = trim((string) config('vpn.sing_box_warp_local_addresses', ''));
        if ($pk === '' || $addrRaw === '') {
            return null;
        }
        $localAddress = $this->parseLocalAddressesList($addrRaw);
        if ($localAddress === []) {
            return null;
        }
        $ob = [
            'type' => 'wireguard',
            'tag' => self::WARP_OUTBOUND_TAG,
            'server' => (string) config('vpn.sing_box_warp_server', 'engage.cloudflareclient.com'),
            'server_port' => (int) config('vpn.sing_box_warp_server_port', 2408),
            'local_address' => $localAddress,
            'private_key' => $pk,
            'peer_public_key' => (string) config('vpn.sing_box_warp_peer_public_key', 'bmXOC+F1FxEMF9dyiK2H5/1SUtzH0JuVo51h2wPfgyo='),
            'mtu' => 1280,
        ];
        $reserved = $this->parseWarpReserved((string) config('vpn.sing_box_warp_reserved', ''));
        if ($reserved !== null) {
            $ob['reserved'] = $reserved;
        }

        return $ob;
    }

    /**
     * @return array<int, string>
     */
    private function parseLocalAddressesList(string $addrRaw): array
    {
        $t = trim($addrRaw);
        if ($t === '') {
            return [];
        }
        if (str_starts_with($t, '[')) {
            $dec = json_decode($t, true);
            if (is_array($dec)) {
                $out = [];
                foreach ($dec as $v) {
                    if (is_string($v) && trim($v) !== '') {
                        $out[] = trim($v);
                    }
                }

                return $out;
            }
        }
        $parts = array_map('trim', explode(',', $t));

        return array_values(array_filter($parts, fn (string $s): bool => $s !== ''));
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseWarpReserved(string $raw): ?array
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        if (preg_match('/^\d+\s*,\s*\d+\s*,\s*\d+$/', $t)) {
            $p = array_map('intval', array_map('trim', explode(',', $t)));
            if (count($p) === 3) {
                return [$p[0], $p[1], $p[2]];
            }
        }
        $d = base64_decode($t, true);
        if ($d !== false && strlen($d) === 3) {
            return [ord($d[0]), ord($d[1]), ord($d[2])];
        }

        return null;
    }
}
