<?php

namespace App\Services\VPN;

/**
 * Разбор subscription-ссылок (vless/vmess/trojan/ss) в структуру прокси Clash Meta.
 * Нужен для клиентов вроде Hiddify, которые не понимают YAML только с proxy-providers.
 */
class ClashProxyFromSubscriptionLink
{
    /**
     * @return array<string, mixed>|null
     */
    public static function parse(string $link): ?array
    {
        $link = trim($link);
        if ($link === '') {
            return null;
        }
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
        if ($scheme === 'vless') {
            return self::parseVless($link);
        }
        if ($scheme === 'vmess') {
            return self::parseVmess($link);
        }
        if ($scheme === 'trojan') {
            return self::parseTrojan($link);
        }
        if ($scheme === 'ss') {
            return self::parseShadowsocks($link);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseVless(string $link): ?array
    {
        $u = parse_url($link);
        if ($u === false || empty($u['host'])) {
            return null;
        }
        $uuid = isset($u['user']) ? rawurldecode((string) $u['user']) : '';
        if ($uuid === '' || ! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
            return null;
        }
        $server = $u['host'];
        $port = isset($u['port']) ? (int) $u['port'] : 443;
        parse_str($u['query'] ?? '', $q);
        $name = isset($u['fragment']) ? rawurldecode((string) $u['fragment']) : '';
        if ($name === '') {
            $name = 'VPN '.$server;
        }
        $name = self::sanitizeProxyName($name);

        $security = strtolower((string) ($q['security'] ?? 'none'));
        $net = strtolower((string) ($q['type'] ?? 'tcp'));

        $proxy = [
            'name' => $name,
            'type' => 'vless',
            'server' => $server,
            'port' => $port,
            'uuid' => $uuid,
            'udp' => true,
        ];

        if ($security === 'tls' || $security === 'reality') {
            $proxy['tls'] = true;
            $sni = (string) ($q['sni'] ?? $q['peer'] ?? '');
            if ($sni !== '') {
                $proxy['servername'] = $sni;
            } elseif (! empty($q['host'])) {
                $proxy['servername'] = (string) $q['host'];
            }
            if ($security === 'reality') {
                $proxy['reality-opts'] = [
                    'public-key' => (string) ($q['pbk'] ?? ''),
                    'short-id' => (string) ($q['sid'] ?? ''),
                ];
                if ($proxy['reality-opts']['public-key'] === '') {
                    unset($proxy['reality-opts']);
                    $proxy['tls'] = true;
                }
            }
        }

        if ($net === 'ws') {
            $proxy['network'] = 'ws';
            $path = (string) ($q['path'] ?? '/');
            $hostHeader = (string) ($q['host'] ?? '');
            $proxy['ws-opts'] = [
                'path' => $path !== '' ? $path : '/',
            ];
            if ($hostHeader !== '') {
                $proxy['ws-opts']['headers'] = ['Host' => $hostHeader];
            }
        } elseif ($net === 'grpc') {
            $proxy['network'] = 'grpc';
            $svc = (string) ($q['serviceName'] ?? $q['servicename'] ?? '');
            if ($svc !== '') {
                $proxy['grpc-opts'] = [
                    'grpc-service-name' => $svc,
                ];
            }
        } else {
            $proxy['network'] = 'tcp';
        }

        return $proxy;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseVmess(string $link): ?array
    {
        $payload = substr($link, strlen('vmess://'));
        $payload = explode('#', $payload, 2);
        $b64 = $payload[0];
        $frag = $payload[1] ?? '';
        $b64 = str_replace(['-', '_'], ['+', '/'], $b64);
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $json = base64_decode($b64, true);
        if ($json === false) {
            return null;
        }
        $cfg = json_decode($json, true);
        if (! is_array($cfg)) {
            return null;
        }
        $add = (string) ($cfg['add'] ?? '');
        if ($add === '') {
            return null;
        }
        $name = (string) ($cfg['ps'] ?? 'VPN');
        if ($frag !== '') {
            $name = rawurldecode($frag);
        }
        $name = self::sanitizeProxyName($name);

        $port = (int) ($cfg['port'] ?? 443);
        $id = (string) ($cfg['id'] ?? '');
        if ($id === '') {
            return null;
        }
        $aid = (int) ($cfg['aid'] ?? 0);
        $net = strtolower((string) ($cfg['net'] ?? 'tcp'));

        $proxy = [
            'name' => $name,
            'type' => 'vmess',
            'server' => $add,
            'port' => $port,
            'uuid' => $id,
            'alterId' => $aid,
            'cipher' => 'auto',
            'udp' => true,
        ];

        if (! empty($cfg['tls']) && $cfg['tls'] === 'tls') {
            $proxy['tls'] = true;
        }

        if ($net === 'ws') {
            $proxy['network'] = 'ws';
            $path = (string) ($cfg['path'] ?? '/');
            $host = (string) ($cfg['host'] ?? '');
            $proxy['ws-opts'] = [
                'path' => $path !== '' ? $path : '/',
            ];
            if ($host !== '') {
                $proxy['ws-opts']['headers'] = ['Host' => $host];
            }
        } elseif ($net === 'grpc') {
            $proxy['network'] = 'grpc';
            $proxy['grpc-opts'] = [
                'grpc-service-name' => (string) ($cfg['path'] ?? ''),
            ];
        } else {
            $proxy['network'] = 'tcp';
        }

        return $proxy;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseTrojan(string $link): ?array
    {
        $u = parse_url($link);
        if ($u === false || empty($u['host'])) {
            return null;
        }
        $password = isset($u['user']) ? rawurldecode((string) $u['user']) : '';
        if ($password === '') {
            return null;
        }
        parse_str($u['query'] ?? '', $q);
        $name = isset($u['fragment']) ? rawurldecode((string) $u['fragment']) : 'Trojan';
        $name = self::sanitizeProxyName($name);

        $proxy = [
            'name' => $name,
            'type' => 'trojan',
            'server' => $u['host'],
            'port' => isset($u['port']) ? (int) $u['port'] : 443,
            'password' => $password,
            'udp' => true,
        ];
        $sni = (string) ($q['sni'] ?? $q['peer'] ?? '');
        if ($sni !== '') {
            $proxy['sni'] = $sni;
        }

        return $proxy;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseShadowsocks(string $link): ?array
    {
        $u = parse_url($link);
        if ($u === false || empty($u['host'])) {
            return null;
        }
        $name = isset($u['fragment']) ? rawurldecode((string) $u['fragment']) : 'SS';
        $name = self::sanitizeProxyName($name);

        $userPart = isset($u['user']) ? (string) $u['user'] : '';
        $port = isset($u['port']) ? (int) $u['port'] : 8388;

        if (strpos($userPart, '@') !== false) {
            $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $userPart), true);
            if ($decoded === false || strpos($decoded, ':') === false) {
                return null;
            }
            [$method, $password] = explode(':', $decoded, 2);

            return [
                'name' => $name,
                'type' => 'ss',
                'server' => $u['host'],
                'port' => $port,
                'cipher' => $method,
                'password' => $password,
                'udp' => true,
            ];
        }

        $b64 = $userPart;
        $b64 = str_replace(['-', '_'], ['+', '/'], $b64);
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($b64, true);
        if ($decoded === false || strpos($decoded, ':') === false) {
            return null;
        }
        [$method, $pass64] = explode(':', $decoded, 2);
        $inner = base64_decode($pass64, true);
        if ($inner !== false && strpos($inner, ':') !== false) {
            [$method, $password] = explode(':', $inner, 2);
        } else {
            $password = $pass64;
        }

        return [
            'name' => $name,
            'type' => 'ss',
            'server' => $u['host'],
            'port' => $port,
            'cipher' => $method,
            'password' => $password,
            'udp' => true,
        ];
    }

    private static function sanitizeProxyName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '';
        $name = trim($name);
        if ($name === '') {
            return 'VPN';
        }
        if (strlen($name) > 120) {
            return substr($name, 0, 120);
        }

        return $name;
    }
}
