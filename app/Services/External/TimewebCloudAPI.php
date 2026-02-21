<?php

namespace App\Services\External;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use InvalidArgumentException;

/**
 * API клиент для работы с Timeweb Cloud API
 * 
 * Документация: https://timeweb.cloud/api-docs
 */
class TimewebCloudAPI
{
    const BASE_URL = 'https://api.timeweb.cloud/api/v1/';
    
    /**
     * Альтернативные базовые URL для тестирования
     */
    private const ALTERNATIVE_BASE_URLS = [
        'https://api.timeweb.cloud/api/v1/',
        'https://api.timeweb.cloud/v1/',
        'https://api.timeweb.cloud/',
    ];
    private string $apiToken;
    
    /**
     * Время последнего запроса для rate limiting
     */
    private static ?float $lastRequestTime = null;
    
    /**
     * Минимальная задержка между запросами (в секундах)
     */
    private const MIN_REQUEST_DELAY = 0.5; // 500ms между запросами

    public function __construct($apiToken)
    {
        if (empty($apiToken)) {
            throw new InvalidArgumentException('Timeweb Cloud API token is required');
        }
        $this->apiToken = $apiToken;
    }

    /**
     * Базовый метод для выполнения запросов
     */
    private function makeRequest(string $endpoint, string $method = 'GET', array $data = []): array
    {
        try {
            // Rate limiting: добавляем задержку между запросами
            if (self::$lastRequestTime !== null) {
                $timeSinceLastRequest = microtime(true) - self::$lastRequestTime;
                if ($timeSinceLastRequest < self::MIN_REQUEST_DELAY) {
                    $sleepTime = self::MIN_REQUEST_DELAY - $timeSinceLastRequest;
                    usleep((int)($sleepTime * 1000000)); // Конвертируем в микросекунды
                }
            }
            self::$lastRequestTime = microtime(true);

            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'TimewebCloud-Client/1.0',
                ],
                'timeout' => 30,
                'connect_timeout' => 10,
            ];

            // Для разных методов используем разные форматы данных
            if (!empty($data)) {
                if ($method === 'GET') {
                    $options['query'] = $data;
                } else {
                    $options['json'] = $data;
                }
            }

            $client = new Client(['base_uri' => self::BASE_URL]);
            
            // Логируем полный URL для отладки
            $fullUrl = self::BASE_URL . $endpoint;
            Log::info('Timeweb Cloud API request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'full_url' => $fullUrl,
                'data' => $data,
                'source' => 'api'
            ]);
            
            $response = $client->request($method, $endpoint, $options);

            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from Timeweb Cloud', [
                    'response' => $result,
                    'endpoint' => $endpoint,
                    'source' => 'api'
                ]);
                throw new RuntimeException('Invalid JSON response from Timeweb Cloud');
            }

            // Timeweb Cloud API возвращает ошибки в формате с полем "error"
            if (isset($data['error'])) {
                $errorMessage = $data['error']['message'] ?? $data['error'] ?? 'Unknown error';
                $errorCode = $data['error']['code'] ?? 'unknown';
                
                Log::error('API error response from Timeweb Cloud', [
                    'response' => $data,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'endpoint' => $endpoint,
                    'source' => 'api'
                ]);

                throw new RuntimeException('Timeweb Cloud API error: ' . $errorMessage);
            }

            return $data;

        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();
            $message = $e->getMessage();
            
            // Получаем тело ответа для более детальной информации
            $responseBody = '';
            if ($e->hasResponse()) {
                try {
                    $responseBody = $e->getResponse()->getBody()->getContents();
                    $responseData = json_decode($responseBody, true);
                    if ($responseData) {
                        $message = $responseData['message'] ?? $responseData['error']['message'] ?? $message;
                    }
                } catch (\Exception $ex) {
                    // Игнорируем ошибки парсинга
                }
            }

            Log::error('Timeweb Cloud API HTTP error', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $message,
                'code' => $statusCode,
                'response_body' => $responseBody,
                'source' => 'api'
            ]);

            if ($statusCode === 401) {
                throw new RuntimeException('Timeweb Cloud API authentication failed. Please check your API token.');
            }

            if ($statusCode === 403) {
                throw new RuntimeException('Timeweb Cloud API access forbidden. Please check: 1) API token is correct, 2) Token has required permissions, 3) API endpoint is correct. Error: ' . $message);
            }

            if ($statusCode === 429) {
                throw new RuntimeException('Timeweb Cloud API rate limit exceeded. Please try again later.');
            }

            throw $e;
        } catch (Exception $e) {
            Log::error('Timeweb Cloud API general error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'api'
            ]);
            throw new RuntimeException('Timeweb Cloud API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Получить информацию об аккаунте
     */
    public function getAccount(): array
    {
        return $this->makeRequest('account');
    }

    /**
     * Получить список серверов
     * Endpoint: GET /api/v1/servers
     */
    public function getServers(array $query = []): array
    {
        $defaults = ['limit' => 100, 'offset' => 0];
        return $this->makeRequest('servers', 'GET', array_merge($defaults, $query));
    }

    /**
     * Получить информацию о сервере по ID
     * Endpoint: GET /api/v1/servers/{id}
     */
    public function getServerById(int $serverId): array
    {
        return $this->makeRequest("servers/{$serverId}");
    }

    /**
     * Создать сервер
     * Пробуем разные варианты endpoints
     * 
     * @param string $name Имя сервера
     * @param string $os Идентификатор ОС (например, 'ubuntu-24.04')
     * @param string $configurationId ID конфигурации сервера или тип ('premium-nvme')
     * @param string $locationId ID локации/региона (например, 'ams-1')
     * @param array $additionalData Дополнительные параметры
     * @return array
     */
    public function createServer(
        string $name,
        string $os,
        string $configurationId,
        string $locationId,
        array $additionalData = []
    ): array {
        // Формируем данные для создания сервера
        // API требует только: name, os_id (или image_id), configuration
        // НЕ принимает: location_id, private_network, backup_enabled, disaster_recovery, ddos_protection, public_ip на верхнем уровне
        $serverData = [
            'name' => $name,
        ];
        
        // Используем os_id или image_id (API требует os_id или image_id как число)
        // Убеждаемся, что os_id - это число
        if (!is_numeric($os)) {
            // Если os не число, возможно это строка (slug), попробуем использовать как image_id
            // Или попробуем найти os_id по slug
            if (is_string($os) && !empty($os)) {
                // Попробуем использовать как image_id (если API поддерживает)
                $serverData['image_id'] = $os;
                Log::warning('Using os value as image_id (not numeric)', ['os' => $os, 'source' => 'api']);
            } else {
                throw new InvalidArgumentException('os_id must be a number. Received: ' . gettype($os) . ' (' . $os . ')');
            }
        } else {
            $serverData['os_id'] = (int)$os;
        }
        
        // API принимает либо configuration (configurator_id + cpu, ram, disk), либо preset_id
        $presetId = isset($additionalData['preset_id']) && is_numeric($additionalData['preset_id'])
            ? (int)$additionalData['preset_id'] : null;
        unset($additionalData['preset_id']);

        if ($presetId !== null) {
            $serverData['preset_id'] = $presetId;
        } else {
            $configuratorId = null;
            if (isset($additionalData['configurator_id']) && is_numeric($additionalData['configurator_id'])) {
                $configuratorId = (int)$additionalData['configurator_id'];
                unset($additionalData['configurator_id']);
            } elseif ($configurationId && $configurationId !== 'premium-nvme' && is_numeric($configurationId)) {
                $configuratorId = (int)$configurationId;
            } else {
                $configuratorId = is_numeric($configurationId) ? (int)$configurationId : null;
            }
            if (!$configuratorId) {
                throw new RuntimeException('configurator_id or preset_id is required for server creation.');
            }
            $serverData['configuration'] = ['configurator_id' => $configuratorId];
            if (isset($additionalData['cpu'])) {
                $serverData['configuration']['cpu'] = (int)$additionalData['cpu'];
                unset($additionalData['cpu']);
            }
            if (isset($additionalData['ram'])) {
                $serverData['configuration']['ram'] = (int)$additionalData['ram'];
                unset($additionalData['ram']);
            }
            if (isset($additionalData['disk'])) {
                $serverData['configuration']['disk'] = (int)$additionalData['disk'];
                unset($additionalData['disk']);
            }
        }

        // Удаляем параметры, которые API не принимает на верхнем уровне
        // Эти параметры могут быть настроены позже через отдельные API вызовы или определяются через configurator_id
        unset($additionalData['location_id']);
        unset($additionalData['private_network']);
        unset($additionalData['backup_enabled']);
        unset($additionalData['backup_auto']);
        unset($additionalData['disaster_recovery']);
        unset($additionalData['ddos_protection']);
        unset($additionalData['public_ip']);
        
        // Если остались другие дополнительные параметры, добавляем их (но только те, которые API принимает)
        if (!empty($additionalData)) {
            Log::warning('Additional parameters that may not be supported by API', [
                'params' => array_keys($additionalData),
                'source' => 'api'
            ]);
            // Не добавляем их автоматически, так как API строго валидирует параметры
        }

        // availability_zone обязателен для создания (Амстердам = ams-1)
        $availabilityZone = $additionalData['availability_zone'] ?? null;
        if ($availabilityZone !== null) {
            $serverData['availability_zone'] = $availabilityZone;
            unset($additionalData['availability_zone']);
        }

        // Без DDoS по умолчанию
        $serverData['is_ddos_guard'] = $additionalData['is_ddos_guard'] ?? false;
        unset($additionalData['is_ddos_guard']);

        // Проект (например VPN-TELEGRAM) — API принимает project_id
        $projectId = $additionalData['project_id'] ?? null;
        if ($projectId !== null && $projectId !== '') {
            $serverData['project_id'] = (int)$projectId;
            unset($additionalData['project_id']);
        }

        if (!empty($additionalData)) {
            $serverData = array_merge($serverData, $additionalData);
        }

        Log::info('Creating Timeweb Cloud server - request data', [
            'server_data' => $serverData,
            'source' => 'api',
            'endpoint' => 'servers'
        ]);

        return $this->makeRequest('servers', 'POST', $serverData);
    }

    /**
     * Удалить сервер
     * Endpoint: DELETE /api/v1/servers/{id}
     */
    public function deleteServer(int $serverId): array
    {
        Log::info('Deleting Timeweb Cloud server', [
            'server_id' => $serverId,
            'source' => 'api'
        ]);
        return $this->makeRequest("servers/{$serverId}", 'DELETE');
    }

    /**
     * Получить список конфигураторов серверов
     * Endpoint: GET /api/v1/configurator/servers (ключ ответа: server_configurators)
     */
    public function getConfigurations(): array
    {
        return $this->makeRequest('configurator/servers');
    }

    /**
     * Получить список тарифов (пресетов) серверов
     * Endpoint: GET /api/v1/presets/servers (ключ ответа: server_presets)
     */
    public function getPresets(): array
    {
        return $this->makeRequest('presets/servers');
    }

    /**
     * Получить список локаций (часто 403 по токену; локации берут из configurator/presets)
     */
    public function getLocations(): array
    {
        try {
            return $this->makeRequest('locations');
        } catch (\Exception $e) {
            Log::warning('Locations endpoint failed (403 typical)', ['error' => $e->getMessage(), 'source' => 'api']);
            throw $e;
        }
    }

    /**
     * Получить список ОС для серверов
     * Endpoint: GET /api/v1/os/servers (ключ ответа: servers_os)
     */
    public function getOperatingSystems(): array
    {
        return $this->makeRequest('os/servers');
    }

    /**
     * Перезагрузить сервер
     * Endpoint: POST /api/v1/servers/{id}/reboot
     */
    public function rebootServer(int $serverId): array
    {
        Log::info('Rebooting Timeweb Cloud server', ['server_id' => $serverId, 'source' => 'api']);
        return $this->makeRequest("servers/{$serverId}/reboot", 'POST');
    }

    /**
     * Выключить сервер
     * Endpoint: POST /api/v1/servers/{id}/shutdown
     */
    public function powerOffServer(int $serverId): array
    {
        Log::info('Powering off Timeweb Cloud server', ['server_id' => $serverId, 'source' => 'api']);
        return $this->makeRequest("servers/{$serverId}/shutdown", 'POST');
    }

    /**
     * Включить сервер
     * Endpoint: POST /api/v1/servers/{id}/start
     */
    public function powerOnServer(int $serverId): array
    {
        Log::info('Powering on Timeweb Cloud server', ['server_id' => $serverId, 'source' => 'api']);
        return $this->makeRequest("servers/{$serverId}/start", 'POST');
    }

    /**
     * Добавить публичный IP к серверу (IPv4 платный, IPv6 бесплатный).
     * POST /api/v1/servers/{server_id}/ips
     *
     * @param int $serverId
     * @param string $type 'ipv4' | 'ipv6'
     * @param string|null $ptr опциональный PTR
     * @return array
     */
    public function addServerIp(int $serverId, string $type = 'ipv4', ?string $ptr = null): array
    {
        $body = ['type' => $type];
        if ($ptr !== null && $ptr !== '') {
            $body['ptr'] = $ptr;
        }
        Log::info('Adding public IP to server', [
            'server_id' => $serverId,
            'type' => $type,
            'source' => 'api'
        ]);
        return $this->makeRequest("servers/{$serverId}/ips", 'POST', $body);
    }

    /**
     * Получение статистики сервера за период (трафик и др.).
     * GET /api/v1/servers/{server_id}/statistics?date_from=...&date_to=...
     *
     * @param int $serverId ID сервера
     * @param string $dateFrom Начало периода (Y-m-d или ISO 8601)
     * @param string $dateTo Конец периода (Y-m-d или ISO 8601)
     * @return array|null Ответ API (структура зависит от API)
     */
    public function getServerStatistics(int $serverId, string $dateFrom, string $dateTo): ?array
    {
        try {
            $query = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];
            return $this->makeRequest("servers/{$serverId}/statistics", 'GET', $query);
        } catch (Exception $e) {
            Log::warning('Timeweb Cloud getServerStatistics failed', [
                'server_id' => $serverId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'error' => $e->getMessage(),
                'source' => 'api',
            ]);
            return null;
        }
    }

    /**
     * Суммировать потребление трафика из ответа API статистики.
     * Поддерживаемые форматы: statistics[].network_in/network_out, statistics[].in/out, data[].*_bytes и т.п.
     *
     * @param array $response Ответ getServerStatistics
     * @return int|null Сумма байт (входящий + исходящий) или null
     */
    private function sumTrafficFromStatisticsResponse(array $response): ?int
    {
        $items = $response['statistics'] ?? $response['data'] ?? $response['stats'] ?? null;
        if (!is_array($items)) {
            return null;
        }
        $total = 0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $in = (int) ($row['network_in'] ?? $row['network_incoming'] ?? $row['in'] ?? $row['incoming_bytes'] ?? 0);
            $out = (int) ($row['network_out'] ?? $row['network_outgoing'] ?? $row['out'] ?? $row['outgoing_bytes'] ?? 0);
            $total += $in + $out;
        }
        return $total > 0 ? $total : null;
    }

    /**
     * Получить данные о трафике сервера (использование за месяц).
     * Трафик у Timeweb безлимитный — лимит не показываем (0), но использование берём из API статистики за текущий и прошлый месяц.
     *
     * @param int $serverId ID сервера в Timeweb Cloud
     * @return array|null Формат как VdsinaAPI::getServerTraffic (limit, current_month, last_month, used, used_percent, ...)
     */
    public function getServerTraffic(int $serverId): ?array
    {
        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $currentStart = $now->format('Y-m-01');
            $currentEnd = $now->format('Y-m-d');
            $lastStart = $now->modify('first day of last month')->format('Y-m-01');
            $lastEnd = $now->modify('last day of last month')->format('Y-m-d');

            $currentMonthBytes = null;
            $lastMonthBytes = null;

            $statsCurrent = $this->getServerStatistics($serverId, $currentStart, $currentEnd);
            if ($statsCurrent !== null) {
                $currentMonthBytes = $this->sumTrafficFromStatisticsResponse($statsCurrent);
            }

            $statsLast = $this->getServerStatistics($serverId, $lastStart, $lastEnd);
            if ($statsLast !== null) {
                $lastMonthBytes = $this->sumTrafficFromStatisticsResponse($statsLast);
            }

            if ($currentMonthBytes === null && $lastMonthBytes === null) {
                $response = $this->getServerById($serverId);
                $data = $response['server'] ?? $response['data'] ?? $response['cloud_server'] ?? $response;
                if (is_array($data)) {
                    if (isset($data['bandwidth']) && is_array($data['bandwidth'])) {
                        $bw = $data['bandwidth'];
                        $currentMonthBytes = isset($bw['current_month']) ? (int) $bw['current_month'] : null;
                        if ($currentMonthBytes === null && isset($bw['in'], $bw['out'])) {
                            $currentMonthBytes = (int) $bw['in'] + (int) $bw['out'];
                        }
                        $lastMonthBytes = isset($bw['last_month']) ? (int) $bw['last_month'] : null;
                    }
                    if ($currentMonthBytes === null && (isset($data['traffic_in']) || isset($data['traffic_out']))) {
                        $currentMonthBytes = (int) ($data['traffic_in'] ?? 0) + (int) ($data['traffic_out'] ?? 0);
                    }
                }
            }

            $usedTraffic = $currentMonthBytes ?? 0;
            $trafficLimitBytes = 0;
            $usedPercent = 0;
            $remaining = 0;
            $remainingPercent = 0;

            return [
                'used' => $usedTraffic,
                'limit' => $trafficLimitBytes,
                'used_percent' => round($usedPercent, 2),
                'remaining' => $remaining,
                'remaining_percent' => round($remainingPercent, 2),
                'current_month' => $currentMonthBytes,
                'last_month' => $lastMonthBytes,
            ];
        } catch (Exception $e) {
            Log::error('Failed to get server traffic from Timeweb Cloud', [
                'server_id' => $serverId,
                'error' => $e->getMessage(),
                'source' => 'api',
            ]);
            return null;
        }
    }

    /**
     * Получить пароль сервера (из объекта server: root_pass)
     */
    public function getServerPassword(int $serverId): array
    {
        $server = $this->getServerById($serverId);
        $data = $server['server'] ?? $server['data'] ?? $server;
        $password = $data['root_pass'] ?? $data['password'] ?? null;
        return $password !== null ? ['password' => $password, 'root_pass' => $password] : [];
    }

    /**
     * Тестирование подключения
     */
    public function testConnection(): array
    {
        try {
            $result = $this->getAccount();

            if (isset($result['account'])) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'account' => $result['account']['email'] ?? 'Unknown',
                    'balance' => $result['account']['balance'] ?? 0
                ];
            }

            return [
                'success' => false,
                'message' => 'API returned unexpected response',
                'response' => $result
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
}

