<?php

namespace App\Services\Server;

use App\Models\Server\Server;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Сводная проверка «рабочих» VPS: доступность заглушки, приманка, опционально полный /test-speed (скачивания + speedtest).
 * Дополнительно: с машины, где крутится Laravel, задержка ICMP/HTTPS до панелей и до списка ваших доменов (config fleet_probe.php).
 */
class ServerFleetProbeService
{
    /**
     * @return array<string, mixed>
     */
    public function probe(Collection $servers, bool $includeTestSpeed): array
    {
        $started = microtime(true);
        $rows = [];
        $summary = [
            'total' => 0,
            'http_ok' => 0,
            'https_ok' => 0,
            'stub_ok_db' => 0,
            'lure_http_ok' => 0,
            'test_speed_ok' => 0,
            'test_speed_fail' => 0,
            'test_speed_skipped' => 0,
        ];

        foreach ($servers as $server) {
            if (! $server instanceof Server) {
                continue;
            }

            /** @var Server $server */
            $ipRaw = trim((string) ($server->ip ?? ''));
            $summary['total']++;

            $base = $this->hostBracket($ipRaw);
            if ($base === null) {
                $rows[] = [
                    'server_id' => $server->id,
                    'name' => $server->name ?? '',
                    'ip' => $ipRaw,
                    'error' => 'Некорректный или пустой IP',
                ];

                continue;
            }

            $stubOkDb = $this->isStubSuccessInDb($server);
            $lureWantedDb = (bool) $server->decoy_stub_include_123_rar;

            $http = $base !== '' ? $this->probeHttpUrl('http://'.$base.'/') : ['ok' => false, 'code' => null, 'ms' => null, 'error' => 'нет хоста'];
            $https = $base !== '' ? $this->probeHttpUrl('https://'.$base.'/') : ['ok' => false, 'code' => null, 'ms' => null, 'error' => 'нет хоста'];

            $lureRow = ['skipped' => true, 'ok' => null, 'code' => null, 'error' => null];
            if ($lureWantedDb && $stubOkDb && $base !== '') {
                $lureRow = array_merge(['skipped' => false], $this->probeHeadOrGet("https://{$base}/123.rar"));
            } elseif ($lureWantedDb && ! $stubOkDb) {
                $lureRow = ['skipped' => false, 'ok' => false, 'code' => null, 'error' => 'В БД приманка вкл., но заглушка не в успешном состоянии'];
            }

            $testSpeed = ['state' => 'skipped', 'excerpt' => null, 'http_code' => null, 'error' => 'опция для этого запуска выключена', 'seconds' => null];
            if ($includeTestSpeed) {
                $token = $server->decoy_stub_test_speed_token;
                if ($stubOkDb && $base !== '' && is_string($token) && $token !== '') {
                    $testSpeed = $this->probeTestSpeed($base, $token);
                } else {
                    $testSpeed['state'] = 'skipped';
                    if (! $stubOkDb) {
                        $testSpeed['error'] = 'нет успешной заглушки';
                    } elseif (! is_string($token) || $token === '') {
                        $testSpeed['error'] = $this->testSpeedSkippedNoTokenHint($server);
                    } else {
                        $testSpeed['error'] = 'нет IP';
                    }
                }
            }

            if ($http['ok'] ?? false) {
                $summary['http_ok']++;
            }
            if ($https['ok'] ?? false) {
                $summary['https_ok']++;
            }
            if ($stubOkDb) {
                $summary['stub_ok_db']++;
            }
            if (! ($lureRow['skipped'] ?? true) && ($lureRow['ok'] ?? false)) {
                $summary['lure_http_ok']++;
            }
            $tsState = $testSpeed['state'] ?? 'skipped';
            if ($tsState === 'ok') {
                $summary['test_speed_ok']++;
            } elseif ($tsState === 'fail') {
                $summary['test_speed_fail']++;
            } elseif ($tsState === 'skipped') {
                $summary['test_speed_skipped']++;
            }

            $rows[] = [
                'server_id' => $server->id,
                'name' => (string) ($server->name ?? ''),
                'ip' => $ipRaw,
                'status_label' => $server->status_label ?? '',
                'stub_ok_db' => $stubOkDb,
                'lure_wanted_db' => $lureWantedDb,
                'http' => $http,
                'https' => $https,
                'lure' => $lureRow,
                'test_speed' => $testSpeed,
            ];
        }

        $elapsed = round((microtime(true) - $started) * 1000);
        $globalProbes = $this->gatherGlobalProbesFromAppHost();

        return [
            'summary' => $summary,
            'rows' => $rows,
            'elapsed_ms' => $elapsed,
            'global_probes' => $globalProbes,
            'text_report' => $this->buildTextReport($rows, $summary, $elapsed, $includeTestSpeed, $globalProbes),
        ];
    }

    /**
     * Проверки с хоста приложения (до панелей и «своих» доменов): ICMP при наличии + HTTPS.
     *
     * @return array<string, mixed>
     */
    public function gatherGlobalProbesFromAppHost(): array
    {
        $cfg = config('fleet_probe', []);
        $preferIcmp = (bool) ($cfg['prefer_icmp'] ?? true);
        $resolver = app(FleetProbeTargetResolver::class);
        $panelRaw = $resolver->mergedPanelHosts();
        $domainsRaw = $resolver->mergedOurDomainHosts();

        $icmpAvailable = $preferIcmp && $this->detectIcmpPingCli();

        return [
            'icmp_cli_available' => $icmpAvailable,
            'meta' => [
                'panels_target_count' => count($panelRaw),
                'our_domains_target_count' => count($domainsRaw),
                'merge_panels_from_db' => (bool) config('fleet_probe.merge_panels_from_db', true),
                'merge_app_domain_hosts' => (bool) config('fleet_probe.merge_app_domain_hosts', true),
            ],
            'panel_hosts' => $this->probeTargetsList($panelRaw, $icmpAvailable),
            'our_domains' => $this->probeTargetsList($domainsRaw, $icmpAvailable),
        ];
    }

    /**
     * @param  array<int, string>  $targets
     * @return array<int, array<string, mixed>>
     */
    private function probeTargetsList(array $targets, bool $tryIcmp): array
    {
        $out = [];
        foreach ($targets as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            $hostForPing = $this->extractHostForPing($raw);
            $httpsUrl = $this->normalizeHttpsProbeUrl($raw);
            $row = [
                'raw' => $raw,
                'host' => $hostForPing,
                'icmp_ms' => null,
                'icmp_error' => null,
                'https' => $this->probeHttpUrlFlexible($httpsUrl),
            ];
            if ($tryIcmp && $hostForPing !== null && $this->isSafeProbeHosttoken($hostForPing)) {
                $icmp = $this->icmpPingOnceMs($hostForPing);
                $row['icmp_ms'] = $icmp['ms'];
                $row['icmp_error'] = $icmp['error'];
            } elseif ($tryIcmp && $hostForPing === null) {
                $row['icmp_error'] = 'не удалось выделить хост для ICMP';
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * CLI ping вызывается только через shell_exec; при disable_functions ICMP отключаем целиком.
     */
    private function isShellExecAllowed(): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }
        $disabled = ini_get('disable_functions');
        if ($disabled === false || trim($disabled) === '') {
            return true;
        }
        foreach (array_map('trim', explode(',', strtolower($disabled))) as $fn) {
            if ($fn === 'shell_exec') {
                return false;
            }
        }

        return true;
    }

    private function detectIcmpPingCli(): bool
    {
        if (PHP_OS_FAMILY === 'Windows' || ! $this->isShellExecAllowed()) {
            return false;
        }
        foreach (['/bin/ping', '/sbin/ping', 'ping'] as $p) {
            if (@is_executable($p)) {
                return true;
            }
        }
        $out = @shell_exec('command -v ping 2>/dev/null');

        return is_string($out) && trim($out) !== '';
    }

    /**
     * Только безопасные метки для shell; IPv4/IPv6 и hostname.
     */
    private function isSafeProbeHosttoken(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return true;
        }
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $inner = substr($host, 1, -1);

            return filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        return (bool) preg_match('/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/', $host);
    }

    private function extractHostForPing(string $target): ?string
    {
        $t = trim($target);
        if ($t === '') {
            return null;
        }
        if (! str_contains($t, '://')) {
            $t = 'https://'.$t;
        }
        $host = parse_url($t, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }
        // Расшифровать punycode не требуем; для брекетов IPv6:
        return strtolower($host);
    }

    private function normalizeHttpsProbeUrl(string $target): string
    {
        $t = trim($target);
        if ($t === '') {
            return '';
        }
        if (! str_contains($t, '://')) {
            return 'https://'.$t.'/';
        }
        // Уже есть схема — как задано; без пути добавим /
        $path = parse_url($t, PHP_URL_PATH);

        return (is_string($path) && $path !== '' && $path !== '/') ? $t : rtrim($t, '/').'/';
    }

    /**
     * @return array{ms: ?float, error: ?string}
     */
    private function icmpPingOnceMs(string $host): array
    {
        if (! $this->isShellExecAllowed()) {
            return ['ms' => null, 'error' => 'ICMP недоступен: shell_exec отключён'];
        }
        if (! $this->isSafeProbeHosttoken($host)) {
            return ['ms' => null, 'error' => 'недопустимое имя хоста'];
        }
        $arg = $host;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && ! str_starts_with($host, '[')) {
            $arg = '['.$host.']';
        }
        if (PHP_OS_FAMILY === 'Linux') {
            $cmd = 'ping -c 1 -W 2 '.escapeshellarg($arg).' 2>/dev/null';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $cmd = 'ping -c 1 -t 2 '.escapeshellarg($arg).' 2>/dev/null';
        } else {
            return ['ms' => null, 'error' => 'ICMP: ОС не поддерживается'];
        }
        $out = shell_exec($cmd);
        if (! is_string($out) || $out === '') {
            return ['ms' => null, 'error' => 'нет ответа ping'];
        }
        if (preg_match('/time[=<]([0-9]+(?:\.[0-9]+)?)\s*ms/i', $out, $m)) {
            return ['ms' => round((float) $m[1], 2), 'error' => null];
        }

        return ['ms' => null, 'error' => 'разбор вывода ping'];
    }

    /**
     * @return array{ok: bool, code: ?int, ms: ?float, error: ?string}
     */
    private function probeHttpUrlFlexible(string $url): array
    {
        if ($url === '') {
            return ['ok' => false, 'code' => null, 'ms' => null, 'error' => 'пустой URL'];
        }
        $cfg = config('fleet_probe', []);
        $t = max(3, min(60, (int) ($cfg['https_timeout'] ?? 12)));
        $ct = max(2, min(20, (int) ($cfg['https_connect_timeout'] ?? 5)));

        try {
            $t0 = microtime(true);
            $res = Http::withoutVerifying()
                ->timeout($t)
                ->withOptions([
                    'connect_timeout' => $ct,
                    'http_errors' => false,
                ])
                ->get($url);
            $ms = round((microtime(true) - $t0) * 1000, 2);
            $code = $res->status();

            return [
                'ok' => $code >= 200 && $code < 500,
                'code' => $code,
                'ms' => $ms,
                'error' => $code >= 200 && $code < 500 ? null : ('HTTP '.$code),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'code' => null, 'ms' => null, 'error' => Str::limit($e->getMessage(), 300)];
        }
    }

    /**
     * Пояснение «пропуск /test-speed»: токен пишется в БД только при ветке «nginx на хосте + fcgi».
     */
    private function testSpeedSkippedNoTokenHint(Server $server): string
    {
        $msg = mb_strtolower((string) ($server->decoy_stub_last_message ?? ''), 'UTF-8');

        if (Str::contains($msg, 'docker')
            || Str::contains($msg, 'контейнер')
            || Str::contains($msg, 'caddy')
            || Str::contains($msg, 'marzban')
            || Str::contains($msg, 'gozargah')) {
            return 'нет токена: /test-speed в сводке только при nginx в ОС + fcgiwrap (заглушка из Docker/Caddy не пишет токен).';
        }

        if (Str::contains($msg, 'исходящие:') || Str::contains($msg, '/test-speed')
            || Str::contains($msg, 'curl -k')) {
            return 'нет токена в БД при тексте про /test-speed — перепримените заглушку или обновите панель и снова «Применить».';
        }

        if (Str::contains($msg, 'nginx установлен') || Str::contains($msg, 'nginx на хосте')) {
            return 'нет токена после установки nginx на хост — перепримените заглушку (нужен fcgiwrap и запись токена).';
        }

        return 'нет токена в БД: он сохраняется только при nginx в ОС и fcgiwrap; при заглушке только в Docker — токена не будет. Перепримените на хосте при необходимости.';
    }

    private function hostBracket(string $ip): ?string
    {
        if ($ip === '') {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return '['.$ip.']';
        }

        return null;
    }

    private function isStubSuccessInDb(Server $server): bool
    {
        if (! $server->decoy_stub_last_applied_at) {
            return false;
        }

        return ! Str::startsWith((string) ($server->decoy_stub_last_message ?? ''), 'Ошибка:');
    }

    /**
     * @return array{ok: bool, code: ?int, ms: ?float, error: ?string}
     */
    private function probeHttpUrl(string $url): array
    {
        try {
            $t0 = microtime(true);
            $res = Http::withoutVerifying()
                ->timeout(14)
                ->withOptions([
                    'connect_timeout' => 6,
                    'http_errors' => false,
                ])
                ->get($url);
            $ms = round((microtime(true) - $t0) * 1000, 2);
            $code = $res->status();

            return [
                'ok' => $code >= 200 && $code < 400,
                'code' => $code,
                'ms' => $ms,
                'error' => $code >= 200 && $code < 400 ? null : ('HTTP '.$code),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'code' => null, 'ms' => null, 'error' => Str::limit($e->getMessage(), 300)];
        }
    }

    /**
     * HEAD, при ошибке методов — короткий GET.
     *
     * @return array{ok: bool, code: ?int, error: ?string}
     */
    private function probeHeadOrGet(string $url): array
    {
        $lastCode = null;
        try {
            foreach (['HEAD', 'GET'] as $method) {
                $pending = Http::withoutVerifying()
                    ->timeout(18)
                    ->withOptions(['connect_timeout' => 6, 'http_errors' => false]);

                $res = $method === 'HEAD'
                    ? $pending->head($url)
                    : $pending->withHeaders(['Range' => 'bytes=0-0'])->get($url);
                $lastCode = $res->status();
                if ($lastCode >= 200 && $lastCode < 400) {
                    return ['ok' => true, 'code' => $lastCode, 'error' => null];
                }
            }

            return ['ok' => false, 'code' => $lastCode, 'error' => 'нет 2xx для /123.rar'];
        } catch (Throwable $e) {
            return ['ok' => false, 'code' => $lastCode, 'error' => Str::limit($e->getMessage(), 300)];
        }
    }

    /**
     * Полный удалённый сценарий на VPS (скачивание тестовых файлов, проверки HTTPS, опционально speedtest).
     * Без ?fleet_check=1 — см. deploy/stub-assets/panel-stub-test-speed.sh.
     *
     * @return array{state: string, excerpt: ?string, http_code: ?int, error: ?string, seconds: ?float}
     */
    private function probeTestSpeed(string $hostBracket, string $token): array
    {
        $url = 'https://'.$hostBracket.'/test-speed?'.http_build_query(['token' => $token]);

        try {
            $t0 = microtime(true);
            $res = Http::withoutVerifying()
                ->timeout(620)
                ->withOptions([
                    'connect_timeout' => 15,
                    'http_errors' => false,
                ])
                ->get($url);

            $sec = round(microtime(true) - $t0, 2);
            $code = $res->status();
            $body = trim(Str::limit($res->body(), 65535));

            if ($code >= 200 && $code < 300) {
                return [
                    'state' => 'ok',
                    'excerpt' => $body !== '' ? $body : '(пустое тело)',
                    'http_code' => $code,
                    'error' => null,
                    'seconds' => $sec,
                ];
            }

            return [
                'state' => 'fail',
                'excerpt' => $body !== '' ? $body : '(пустое тело)',
                'http_code' => $code,
                'error' => 'код '.$code.' (HTTPS)',
                'seconds' => $sec,
            ];
        } catch (Throwable $e) {
            return [
                'state' => 'fail',
                'excerpt' => null,
                'http_code' => null,
                'error' => Str::limit($e->getMessage(), 500),
                'seconds' => null,
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $summary
     * @param  array<string, mixed>  $globalProbes
     */
    private function buildTextReport(array $rows, array $summary, float $elapsedMs, bool $includeTestSpeed, array $globalProbes = []): string
    {
        $lines = [];
        $lines[] = 'Сводная проверка VPS — '.now()->format('Y-m-d H:i:s');
        $lines[] = 'Обработано: '.$summary['total'].' серверов, время выполнения: '.number_format((float) $elapsedMs).' мс.';
        $lines[] = $this->buildGlobalProbesTextBlock($globalProbes);
        $lines[] = 'HTTP OK: '.$summary['http_ok'].'  | HTTPS OK: '.$summary['https_ok'].'  | заглушка OK (БД): '.$summary['stub_ok_db'];
        if ($includeTestSpeed) {
            $lines[] = '/test-speed: успех '.$summary['test_speed_ok'].', ошибок '.$summary['test_speed_fail'].', пропуск '.$summary['test_speed_skipped'].'.';
        } else {
            $lines[] = '/test-speed: не запускался (опция отключена).';
        }
        $lines[] = '';

        foreach ($rows as $r) {
            if (isset($r['error']) && isset($r['ip'])) {
                $lines[] = "--- #{$r['server_id']} {$r['name']} ({$r['ip']}) — {$r['error']}";

                continue;
            }
            $lines[] = "--- #{$r['server_id']} {$r['name']} ({$r['ip']}) статус {$r['status_label']}";
            $lines[] = '  Заглушка БД OK: '.($r['stub_ok_db'] ? 'да' : 'нет').' ; приманка в БД: '.($r['lure_wanted_db'] ?? false ? 'да' : 'нет');
            /** @phpstan-ignore-next-line keys */
            $lines[] = '  HTTP '.$this->probeLineVerbose($r['http'] ?? []);
            /** @phpstan-ignore-next-line */
            $lines[] = '  HTTPS '.$this->probeLineVerbose($r['https'] ?? []);
            $lur = $r['lure'] ?? [];
            if (($lur['skipped'] ?? true)) {
                $lines[] = '  /123.rar: проверка пропущена';
            } else {
                $lines[] = '  /123.rar — '.(($lur['ok'] ?? false) ? 'ОК код '.($lur['code'] ?? '') : 'нет: '.($lur['error'] ?? ''));
            }
            $ts = $r['test_speed'] ?? [];
            if (($ts['state'] ?? '') === 'skipped') {
                $lines[] = '  /test-speed: пропуск — '.($ts['error'] ?? '');
            } else {
                $sec = isset($ts['seconds']) ? (string) $ts['seconds'].' с' : '';
                $lines[] = '  /test-speed: '.(($ts['state'] ?? '') === 'ok' ? 'ОК '.$sec : (($ts['error'] ?? 'ошибка').' '.$sec));
                $ex = isset($ts['excerpt']) ? Str::limit((string) $ts['excerpt'], 38000) : '';
                if ($ex !== '') {
                    foreach (preg_split("/\r\n|\r|\n/", $ex, -1, PREG_SPLIT_NO_EMPTY) as $pex) {
                        $lines[] = '    '.$pex;
                        if (count($lines) > 260) {
                            break 2;
                        }
                    }
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $globalProbes
     */
    private function buildGlobalProbesTextBlock(array $globalProbes): string
    {
        $lines = [];
        $lines[] = '--- С хоста Laravel до панелей и доменов (таблица panel + .env FLEET_PROBE_* + опционально APP_URL/зеркала) ---';
        $lines[] = 'ICMP через CLI: '.(! empty($globalProbes['icmp_cli_available']) ? 'да' : 'нет');
        if (! empty($globalProbes['meta']) && is_array($globalProbes['meta'])) {
            $m = $globalProbes['meta'];
            $lines[] = sprintf(
                'Целей: панели %d, домены %d; merge БД=%s, merge APP=%s',
                (int) ($m['panels_target_count'] ?? 0),
                (int) ($m['our_domains_target_count'] ?? 0),
                ! empty($m['merge_panels_from_db']) ? 'да' : 'нет',
                ! empty($m['merge_app_domain_hosts']) ? 'да' : 'нет'
            );
        }

        foreach (['panel_hosts' => 'Панели', 'our_domains' => 'Наши домены'] as $key => $label) {
            $list = $globalProbes[$key] ?? [];
            if (! is_array($list) || $list === []) {
                $lines[] = $label.': (список пуст)';

                continue;
            }
            $lines[] = $label.':';
            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $raw = (string) ($item['raw'] ?? '');
                $icmpMs = $item['icmp_ms'] ?? null;
                $icmpErr = $item['icmp_error'] ?? null;
                $h = $item['https'] ?? [];
                $icmpPart = $icmpMs !== null
                    ? ('ICMP ~'.(string) $icmpMs.' мс')
                    : ('ICMP: '.($icmpErr !== null ? (string) $icmpErr : '—'));
                $lines[] = '  '.$raw.' | '.$icmpPart.' | HTTPS '.$this->probeLineVerbose(is_array($h) ? $h : []);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{ok?: bool, code?: mixed, ms?: mixed, error?: mixed}  $p
     */
    private function probeLineVerbose(array $p): string
    {
        $code = isset($p['code']) ? (string) $p['code'] : '?';
        $ms = isset($p['ms']) ? (string) $p['ms'].' мс' : '';
        $st = ($p['ok'] ?? false) ? 'OK' : 'нет';

        return $st.' код '.$code.($ms !== '' ? ' ('.$ms.')' : '').(isset($p['error']) && $p['error'] !== null ? ', '.$p['error'] : '');
    }
}
