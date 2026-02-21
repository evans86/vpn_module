<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

/**
 * Проверка запросов к Timeweb Cloud API.
 * Выполняет запросы из документации и выводит ответы для проверки.
 *
 * Запуск: php artisan timeweb:check-requests
 * Один эндпоинт: php artisan timeweb:check-requests --only=servers
 * Сохранить в файл: php artisan timeweb:check-requests --save=responses.json
 */
class TimewebCheckRequestsCommand extends Command
{
    protected $signature = 'timeweb:check-requests
                            {--only= : Проверить только один эндпоинт (servers|os|configurator|presets|software|locations|account)}
                            {--save= : Путь к файлу для сохранения ответов (JSON)}
                            {--limit=5 : Для списка серверов — макс. записей (query limit)}
                            {--strict : Считать любую 4xx/5xx ошибкой (по умолчанию 404/403 не ломают код выхода)}';

    protected $description = 'Проверка запросов к Timeweb Cloud API (справочники и серверы)';

    private const BASE_URL = 'https://api.timeweb.cloud/api/v1/';

    /** Список запросов для проверки: [ 'ключ' => [ 'path' => '...', 'method' => 'GET', 'query' => [] ] ] */
    private function getRequests(): array
    {
        $limit = (int) $this->option('limit');
        $limit = $limit >= 1 && $limit <= 100 ? $limit : 5;

        return [
            'account' => [
                'path'   => 'account',
                'method' => 'GET',
                'query'  => [],
                'title'  => 'Аккаунт (account)',
            ],
            'servers' => [
                'path'   => 'servers',
                'method' => 'GET',
                'query'  => ['limit' => $limit],
                'title'  => 'Список серверов (servers)',
            ],
            'os' => [
                'path'   => 'os/servers',
                'method' => 'GET',
                'query'  => [],
                'title'  => 'ОС для серверов (os/servers)',
            ],
            'configurator' => [
                'path'   => 'configurator/servers',
                'method' => 'GET',
                'query'  => [],
                'title'  => 'Конфигураторы (configurator/servers)',
            ],
            'presets' => [
                'path'   => 'presets/servers',
                'method' => 'GET',
                'query'  => [],
                'title'  => 'Тарифы/пресеты (presets/servers)',
            ],
            'software' => [
                'path'   => 'software/servers',
                'method' => 'GET',
                'query'  => [],
                'title'  => 'ПО маркетплейса (software/servers)',
            ],
            'locations' => [
                'path'   => 'locations',
                'method' => 'GET',
                'query'  => [],
                'title'  => 'Локации (locations)',
            ],
        ];
    }

    public function handle(): int
    {
        $token = config('services.api_keys.timeweb_key');
        if (empty($token)) {
            $this->error('Токен не задан. Укажите TIMEWEB_API_KEY в .env и config/services.php.');
            return self::FAILURE;
        }

        $only = $this->option('only');
        $savePath = $this->option('save');
        $requests = $this->getRequests();

        if ($only !== null) {
            $only = strtolower(trim($only));
            if (!isset($requests[$only])) {
                $this->error("Неизвестный эндпоинт: --only={$only}. Доступно: " . implode(', ', array_keys($requests)));
                return self::FAILURE;
            }
            $requests = [$only => $requests[$only]];
        }

        $client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout'  => 30,
            'headers'  => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'         => 'application/json',
                'Content-Type'   => 'application/json',
            ],
        ]);

        $results = [];
        $hasError = false;
        $strict = (bool) $this->option('strict');

        foreach ($requests as $key => $req) {
            $path = $req['path'];
            $method = $req['method'];
            $query = $req['query'];
            $title = $req['title'];

            $uri = $path . ($query ? '?' . http_build_query($query) : '');
            $fullUrl = self::BASE_URL . $uri;

            $this->line('');
            $this->info("--- {$title} ---");
            $this->line("  {$method} {$fullUrl}");

            try {
                $response = $client->request($method, $uri, $query ? ['query' => $query] : []);
                $status = $response->getStatusCode();
                $body = (string) $response->getBody();
                $data = json_decode($body, true);

                $this->line("  Ответ: HTTP {$status}");

                if (is_array($data)) {
                    $summary = $this->summarizeResponse($key, $data);
                    foreach ($summary as $line) {
                        $this->line('  ' . $line);
                    }
                    $this->line('<comment>  Тело (первые 2000 символов):</comment>');
                    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $preview = mb_strlen($encoded) > 2000 ? mb_substr($encoded, 0, 2000) . "\n  ... (обрезано)" : $encoded;
                    $this->line($this->indentMultiline($preview, 2));

                    $results[$key] = [
                        'url'    => $fullUrl,
                        'status' => $status,
                        'data'   => $data,
                    ];
                } else {
                    $this->line('  Тело (не JSON): ' . mb_substr($body, 0, 500));
                    $results[$key] = ['url' => $fullUrl, 'status' => $status, 'raw' => $body];
                }
            } catch (GuzzleException $e) {
                $code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
                $responseBody = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
                // 404/403 по части эндпоинтов ожидаемы (account — нет в API, locations — 403 по правам)
                $softError = ($code === 404 || $code === 403) && !$strict;
                if (!$softError) {
                    $hasError = true;
                }
                $this->error("  Ошибка: HTTP {$code} — " . $e->getMessage());
                if ($softError) {
                    $this->line('  <comment>(404/403 не считаются ошибкой без --strict; локации можно брать из configurator/presets)</comment>');
                }
                if ($responseBody !== '') {
                    $decoded = json_decode($responseBody, true);
                    $preview = $decoded ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $responseBody;
                    $preview = mb_strlen($preview) > 1500 ? mb_substr($preview, 0, 1500) . "\n  ..." : $preview;
                    $this->line($this->indentMultiline($preview, 2));
                }
                $results[$key] = [
                    'url'     => $fullUrl,
                    'error'   => true,
                    'code'    => $code,
                    'message' => $e->getMessage(),
                    'body'    => $responseBody,
                ];
            }
        }

        $this->printSummary($results, $hasError);

        if ($savePath !== null && $savePath !== '') {
            $dir = dirname($savePath);
            if ($dir !== '.' && !is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $written = file_put_contents($savePath, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($written !== false) {
                $this->info("\nОтветы сохранены в: {$savePath}");
            } else {
                $this->warn("\nНе удалось сохранить файл: {$savePath}");
            }
        }

        $this->line('');
        return $hasError ? self::FAILURE : self::SUCCESS;
    }

    private function printSummary(array $results, bool $hasError): void
    {
        $ok = [];
        $failed = [];
        foreach ($results as $key => $r) {
            if (!empty($r['error'])) {
                $failed[$key] = $r['code'] ?? '?';
            } else {
                $ok[] = $key;
            }
        }
        $this->newLine();
        $this->line('<info>Итог:</info> ' . count($ok) . ' OK (' . implode(', ', $ok) . ')');
        if (!empty($failed)) {
            $parts = [];
            foreach ($failed as $k => $code) {
                $parts[] = "{$k}=HTTP {$code}";
            }
            $this->line('<comment>Ошибки (не критично для создания серверов):</comment> ' . implode(', ', $parts));
        }
    }

    private function summarizeResponse(string $key, array $data): array
    {
        $lines = [];
        if (isset($data['meta']['total'])) {
            $lines[] = "meta.total: {$data['meta']['total']}";
        }
        foreach (['servers', 'servers_os', 'server_configurators', 'server_presets', 'servers_software'] as $listKey) {
            if (isset($data[$listKey]) && is_array($data[$listKey])) {
                $lines[] = "{$listKey}: " . count($data[$listKey]) . " записей";
            }
        }
        if (isset($data['server']) && is_array($data['server'])) {
            $lines[] = 'server: 1 объект';
        }
        if (isset($data['response_id'])) {
            $lines[] = "response_id: {$data['response_id']}";
        }
        return $lines;
    }

    private function indentMultiline(string $text, int $spaces): string
    {
        $pad = str_repeat(' ', $spaces);
        return $pad . str_replace("\n", "\n" . $pad, $text);
    }
}
