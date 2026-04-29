<?php

namespace App\Services\Server;

use App\Models\Server\Server;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Сводная проверка «рабочих» VPS: доступность заглушки, приманка, опционально /test-speed.
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
                    $testSpeed['error'] = ! $stubOkDb ? 'нет успешной заглушки' : ((! is_string($token) || $token === '') ? 'нет токена в БД (повторите «Применить заглушку» при работающем fcgiwrap)' : 'нет IP');
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

        return [
            'summary' => $summary,
            'rows' => $rows,
            'elapsed_ms' => $elapsed,
            'text_report' => $this->buildTextReport($rows, $summary, $elapsed, $includeTestSpeed),
        ];
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
     * Тяжёлый удалённый сценарий (до ~3 мин по таймауту Laravel HTTP).
     *
     * @return array{state: string, excerpt: ?string, http_code: ?int, error: ?string, seconds: ?float}
     */
    private function probeTestSpeed(string $hostBracket, string $token): array
    {
        $query = http_build_query([
            'token' => $token,
            'fleet_check' => '1',
        ]);
        $httpsUrl = 'https://'.$hostBracket.'/test-speed?'.$query;
        $httpUrl = 'http://'.$hostBracket.'/test-speed?'.$query;

        try {
            $t0 = microtime(true);
            $res = Http::withoutVerifying()
                ->timeout(45)
                ->withOptions([
                    'connect_timeout' => 10,
                    'http_errors' => false,
                ])
                ->get($httpsUrl);

            $sec = round(microtime(true) - $t0, 2);
            $code = $res->status();
            $body = $res->body();
            $excerptBase = trim(Str::limit($body, 4000));

            if ($code >= 200 && $code < 300) {
                return [
                    'state' => 'ok',
                    'excerpt' => $excerptBase !== '' ? $excerptBase : '(пустое тело)',
                    'http_code' => $code,
                    'error' => null,
                    'seconds' => $sec,
                ];
            }

            // Частый случай гонки: nginx смотрит не в тот UNIX-сокет; по HTTP может отрабатывать тот же vhost без http2-тонкости
            $tryHttpStatuses = [502, 503, 504];
            if (in_array($code, $tryHttpStatuses, true)) {
                $t1 = microtime(true);
                $httpRes = Http::withoutVerifying()
                    ->timeout(45)
                    ->withOptions([
                        'connect_timeout' => 10,
                        'http_errors' => false,
                    ])
                    ->get($httpUrl);
                $sec2 = round(microtime(true) - $t1, 2);
                $hCode = $httpRes->status();
                $hBody = trim(Str::limit($httpRes->body(), 4000));

                if ($hCode >= 200 && $hCode < 300) {
                    $note = 'HTTPS '.$code.', по HTTP — '.$hCode.' (проверьте сокет fcgiwrap/nginx; повторите «Применить заглушку»). ';
                    $excerpt = trim($note.($hBody !== '' ? $hBody : '(пустое тело)'));

                    return [
                        'state' => 'ok',
                        'excerpt' => $excerpt,
                        'http_code' => $hCode,
                        'error' => null,
                        'seconds' => $sec2,
                    ];
                }
            }

            return [
                'state' => 'fail',
                'excerpt' => $excerptBase !== '' ? $excerptBase : '(пустое тело)',
                'http_code' => $code,
                'error' => 'HTTP '.$code,
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
     */
    private function buildTextReport(array $rows, array $summary, float $elapsedMs, bool $includeTestSpeed): string
    {
        $lines = [];
        $lines[] = 'Сводная проверка VPS — '.now()->format('Y-m-d H:i:s');
        $lines[] = 'Обработано: '.$summary['total'].' серверов, время выполнения: '.number_format((float) $elapsedMs).' мс.';
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
                $ex = isset($ts['excerpt']) ? Str::limit((string) $ts['excerpt'], 2200) : '';
                if ($ex !== '') {
                    foreach (preg_split("/\r\n|\r|\n/", $ex, -1, PREG_SPLIT_NO_EMPTY) as $pex) {
                        $lines[] = '    '.$pex;
                        if (count($lines) > 120) {
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
