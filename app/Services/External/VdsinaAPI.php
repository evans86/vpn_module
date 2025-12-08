<?php

namespace App\Services\External;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use InvalidArgumentException;

class VdsinaAPI
{
    const BASE_URL = 'https://userapi.vdsina.com/v1/';
    private string $apiKey;

    public function __construct($apiKey)
    {
        if (empty($apiKey)) {
            throw new InvalidArgumentException('VDSina API key is required');
        }
        $this->apiKey = $apiKey;
    }

    /**
     * Базовый метод для выполнения запросов
     */
    private function makeRequest(string $action, string $method = 'GET', array $data = []): array
    {
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'VDSina-Client/1.0',
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
            $response = $client->request($method, $action, $options);

            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'response' => $result,
                    'source' => 'api'
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('API error response from VDSina', [
                    'response' => $data,
                    'status_code' => $data['status_code'] ?? 'unknown',
                    'source' => 'api'
                ]);

                throw new RuntimeException('VDSina API error: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            return $data;

        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();
            $message = $e->getMessage();

            Log::error('VDSina API HTTP error', [
                'action' => $action,
                'method' => $method,
                'error' => $message,
                'code' => $statusCode,
                'source' => 'api'
            ]);

            if ($statusCode === 401) {
                throw new RuntimeException('VDSina API authentication failed. Please check your API token.');
            }

            throw $e;
        } catch (Exception $e) {
            Log::error('VDSina API general error', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'api'
            ]);
            throw new RuntimeException('VDSina API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Получить пароль сервера через специальный эндпоинт
     */
    public function getServerPassword(int $serverId): array
    {
        return $this->makeRequest("server.password/{$serverId}");
    }

    /**
     * Получить информацию об аккаунте
     */
    public function getAccount(): array
    {
        return $this->makeRequest('account');
    }

    /**
     * Получить список дата-центров
     */
    public function getDatacenter(): array
    {
        return $this->makeRequest('datacenter');
    }

    /**
     * Получить группы серверов
     */
    public function getServerGroup(): array
    {
        return $this->makeRequest('server-group');
    }

    /**
     * Получить шаблоны ОС
     */
    public function getTemplate(): array
    {
        return $this->makeRequest('template');
    }

    /**
     * Получить тарифные планы для группы серверов
     */
    public function getServerPlan(int $serverGroupId): array
    {
        return $this->makeRequest("server-plan/{$serverGroupId}");
    }

    /**
     * Получить список серверов
     */
    public function getServers(): array
    {
        return $this->makeRequest('server');
    }

    /**
     * Получить информацию о сервере
     */
    public function getServerById(int $serverId): array
    {
        return $this->makeRequest("server/{$serverId}");
    }

    /**
     * Создать сервер
     */
    public function createServer(
        string $server_name,
        int    $server_plan,
        int    $autoprolong = 0,
        int    $datacenter = 1,
        int    $template = 23,
        int    $backup_auto = 0
    ): array
    {
        $serverData = [
            'name' => $server_name,
            'server-plan' => $server_plan,
            'autoprolong' => $autoprolong,
            'datacenter' => $datacenter,
            'template' => $template,
            'backup_auto' => $backup_auto
        ];

        Log::info('Creating VDSina server', array_merge($serverData, ['source' => 'api']));

        return $this->makeRequest('server', 'POST', $serverData);
    }

    /**
     * Удалить сервер
     */
    public function deleteServer(int $serverId): array
    {
        Log::info('Deleting VDSina server', [
            'server_id' => $serverId,
            'source' => 'api'
        ]);

        return $this->makeRequest("server/{$serverId}", 'DELETE');
    }

    /**
     * Удалить все бэкапы
     */
    public function deleteAllBackups(int $serverId): array
    {
        Log::info('Deleting VDSina server backups', [
            'server_id' => $serverId,
            'source' => 'api'
        ]);

        return $this->makeRequest("server.schedule/{$serverId}", 'DELETE');
    }

    /**
     * Перезагрузить сервер
     */
    public function rebootServer(int $serverId): array
    {
        Log::info('Rebooting VDSina server', [
            'server_id' => $serverId,
            'source' => 'api'
        ]);

        return $this->makeRequest("server.reboot/{$serverId}", 'PUT');
    }

    /**
     * Выключить сервер
     */
    public function powerOffServer(int $serverId): array
    {
        Log::info('Powering off VDSina server', [
            'server_id' => $serverId,
            'source' => 'api'
        ]);

        return $this->makeRequest("server.poweroff/{$serverId}", 'PUT');
    }

    /**
     * Включить сервер
     */
    public function powerOnServer(int $serverId): array
    {
        Log::info('Powering on VDSina server', [
            'server_id' => $serverId,
            'source' => 'api'
        ]);

        return $this->makeRequest("server.poweron/{$serverId}", 'PUT');
    }

    /**
     * Получить информацию о трафике сервера
     * Возвращает использованный трафик и лимит
     * 
     * Согласно документации VDSINA API:
     * - bandwidth.current_month - использованный трафик за текущий месяц (в байтах)
     * - bandwidth.last_month - использованный трафик за прошлый месяц (в байтах)
     * - data.traff.bytes - лимит трафика включенный в тариф (в байтах)
     * 
     * @param int $serverId ID сервера в VDSINA
     * @return array|null Массив с данными о трафике или null при ошибке
     */
    public function getServerTraffic(int $serverId): ?array
    {
        try {
            $serverData = $this->getServerById($serverId);
            
            if (!isset($serverData['data'])) {
                Log::warning('VDSINA API: No data in server response', ['server_id' => $serverId]);
                return null;
            }
            
            $data = $serverData['data'];
            
            // Получаем использованный трафик за текущий месяц
            // Согласно документации: bandwidth.current_month - Total traffic in bytes for current month
            $usedTraffic = null;
            if (isset($data['bandwidth']['current_month'])) {
                $usedTraffic = (int)$data['bandwidth']['current_month'];
            } elseif (isset($data['bandwidth']['last_month'])) {
                // Если текущего месяца нет, используем прошлый месяц
                $usedTraffic = (int)$data['bandwidth']['last_month'];
            }
            
            // Получаем лимит трафика из тарифа
            // Согласно документации: data.traff.bytes - Amount of network bandwidth in bytes
            $trafficLimit = null;
            if (isset($data['data']['traff']['bytes'])) {
                $trafficLimit = (int)$data['data']['traff']['bytes'];
            } elseif (isset($data['data']['traff']['value'])) {
                // Если bytes нет, но есть value, конвертируем из TB в байты
                // value обычно в TB согласно документации (for: "Tb")
                $valueInTb = (int)$data['data']['traff']['value'];
                $trafficLimit = $valueInTb * 1024 * 1024 * 1024 * 1024; // TB в байты
            }
            
            // Если лимит не указан, используем значение из конфига (по умолчанию 32TB)
            if ($trafficLimit === null || $trafficLimit === 0) {
                $trafficLimit = config('panel.server_traffic_limit', 32 * 1024 * 1024 * 1024 * 1024);
            }
            
            // Если использованный трафик не указан, считаем что 0
            if ($usedTraffic === null) {
                $usedTraffic = 0;
            }
            
            $usedPercent = $trafficLimit > 0 ? (($usedTraffic / $trafficLimit) * 100) : 0;
            $remaining = max(0, $trafficLimit - $usedTraffic);
            $remainingPercent = $trafficLimit > 0 ? (($remaining / $trafficLimit) * 100) : 0;
            
            $currentMonthBytes = isset($data['bandwidth']['current_month']) ? (int)$data['bandwidth']['current_month'] : null;
            $lastMonthBytes = isset($data['bandwidth']['last_month']) ? (int)$data['bandwidth']['last_month'] : null;
            Log::info('Bandwidth data processed', [
                'last_month_processed' => $lastMonthBytes,
                'source' => 'api'
            ]);
            
            return [
                'used' => $usedTraffic,
                'limit' => $trafficLimit,
                'used_percent' => round($usedPercent, 2),
                'remaining' => $remaining,
                'remaining_percent' => round($remainingPercent, 2),
                'current_month' => $currentMonthBytes,
                'last_month' => $lastMonthBytes,
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to get server traffic from VDSINA', [
                'server_id' => $serverId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Тестирование подключения
     */
    public function testConnection(): array
    {
        try {
            $result = $this->getAccount();

            if (isset($result['status']) && $result['status'] === 'ok') {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'account' => $result['data']['email'] ?? 'Unknown',
                    'balance' => $result['data']['balance'] ?? 0
                ];
            }

            return [
                'success' => false,
                'message' => 'API returned error: ' . ($result['status_msg'] ?? 'Unknown error'),
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
