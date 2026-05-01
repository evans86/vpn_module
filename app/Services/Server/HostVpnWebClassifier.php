<?php

namespace App\Services\Server;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Эвристика «похоже на веб / похоже на VPN» по IP или домену (для страницы проверки флота).
 */
class HostVpnWebClassifier
{
    /** @var list<int> */
    private const VPN_HINT_PORTS = [443, 80, 8443, 9443, 2083, 2053, 2096, 8080];

    /**
     * @return array<string, mixed>
     */
    public function classify(string $input): array
    {
        $trim = trim($input);
        if ($trim === '') {
            return [
                'ok' => false,
                'error' => 'Укажите домен или IPv4.',
            ];
        }

        $hostOnly = $this->extractProbeHost($trim);
        if ($hostOnly === null) {
            return [
                'ok' => false,
                'error' => 'Не удалось разобрать хост.',
            ];
        }

        $ip = $this->resolveIpv4($hostOnly);

        $openPorts = [];
        if ($ip !== null) {
            foreach (self::VPN_HINT_PORTS as $port) {
                if ($this->tcpReachable($ip, $port, 2)) {
                    $openPorts[] = $port;
                }
            }
        }

        $scoreVpn = 0;
        $scoreWeb = 0;
        foreach ($openPorts as $p) {
            if (in_array($p, [8443, 9443, 2083, 2053, 2096, 8080], true)) {
                $scoreVpn += 2;
            }
            if (in_array($p, [80, 443], true)) {
                $scoreWeb += 1;
            }
        }

        $httpsProbe = ['ok' => false, 'status' => null, 'server' => null, 'ms' => null, 'summary' => 'не проверено'];
        if (filter_var($hostOnly, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $probeUrl = 'https://'.$hostOnly.'/';
        } elseif (filter_var($hostOnly, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $probeUrl = 'https://['.$hostOnly.']/';
        } else {
            $probeUrl = 'https://'.$hostOnly.'/';
        }

        $httpsProbe = $this->probeHttpsHeaders($probeUrl);
        $serverHdr = strtolower((string) ($httpsProbe['server'] ?? ''));

        if ($serverHdr !== '') {
            if (str_contains($serverHdr, 'nginx') || str_contains($serverHdr, 'apache') || str_contains($serverHdr, 'caddy')) {
                $scoreWeb += 2;
            }
            if (str_contains($serverHdr, 'cloudflare')) {
                $scoreWeb += 1;
            }
        }

        if (($httpsProbe['ok'] ?? false) && $serverHdr === '') {
            $scoreWeb += 1;
        }

        $verdict = 'неоднозначно';
        if ($scoreVpn >= 4 && $scoreVpn > $scoreWeb) {
            $verdict = 'vpn';
        } elseif ($scoreWeb >= $scoreVpn) {
            $verdict = 'web';
        }

        if ($verdict === 'vpn') {
            $verdictLabel = 'Скорее VPN/прокси-нода';
        } elseif ($verdict === 'web') {
            $verdictLabel = 'Скорее обычный веб (80/443, типичные заголовки)';
        } else {
            $verdictLabel = 'Неоднозначно — смотрите порты и HTTPS';
        }

        return [
            'ok' => true,
            'input' => $trim,
            'host' => $hostOnly,
            'ipv4' => $ip,
            'open_ports' => $openPorts,
            'score_vpn' => $scoreVpn,
            'score_web' => $scoreWeb,
            'verdict' => $verdict,
            'verdict_label' => $verdictLabel,
            'https' => $httpsProbe,
        ];
    }

    private function extractProbeHost(string $input): ?string
    {
        $s = trim($input);
        if ($s === '') {
            return null;
        }
        if (! str_contains($s, '://')) {
            $s = 'https://'.$s.'/';
        }
        $host = parse_url($s, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower($host);
    }

    private function resolveIpv4(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return null;
        }
        $ip = @gethostbyname($host);
        if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }

        return null;
    }

    private function tcpReachable(string $ipv4, int $port, int $timeoutSec): bool
    {
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($ipv4, $port, $errno, $errstr, $timeoutSec);
        if ($fp !== false) {
            fclose($fp);

            return true;
        }

        return false;
    }

    /**
     * @return array{ok: bool, status: ?int, server: ?string, ms: ?float, summary: string}
     */
    private function probeHttpsHeaders(string $url): array
    {
        try {
            $t0 = microtime(true);
            $res = Http::withoutVerifying()
                ->timeout(10)
                ->withOptions(['connect_timeout' => 4, 'http_errors' => false])
                ->withHeaders(['User-Agent' => 'VPN-Fleet-Classifier/1'])
                ->head($url);
            $ms = round((microtime(true) - $t0) * 1000, 2);
            $code = $res->status();
            $server = $res->header('Server');
            $ok = $code >= 200 && $code < 500;

            return [
                'ok' => $ok,
                'status' => $code,
                'server' => is_string($server) ? Str::limit($server, 120) : null,
                'ms' => $ms,
                'summary' => 'HEAD '.$code.($server ? ' Server: '.$server : ''),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'server' => null,
                'ms' => null,
                'summary' => Str::limit($e->getMessage(), 200),
            ];
        }
    }
}
