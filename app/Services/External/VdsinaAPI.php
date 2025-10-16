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

            Log::info('VDSina API Request', [
                'action' => $action,
                'method' => $method,
                'data_keys' => array_keys($data)
            ]);

            $client = new Client(['base_uri' => self::BASE_URL]);
            $response = $client->request($method, $action, $options);

            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);

            if (!is_array($data)) {
                Log::error('Invalid JSON response from VDSina', [
                    'response' => $result
                ]);
                throw new RuntimeException('Invalid JSON response from VDSina');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                Log::error('API error response from VDSina', [
                    'response' => $data,
                    'status_code' => $data['status_code'] ?? 'unknown'
                ]);

                throw new RuntimeException('VDSina API error: ' . ($data['status_msg'] ?? 'Unknown error'));
            }

            Log::info('VDSina API Response Success', [
                'action' => $action,
                'data_count' => isset($data['data']) ? (is_array($data['data']) ? count($data['data']) : 'single') : 'none'
            ]);

            return $data;

        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();
            $message = $e->getMessage();

            Log::error('VDSina API HTTP error', [
                'action' => $action,
                'method' => $method,
                'error' => $message,
                'code' => $statusCode
            ]);

            if ($statusCode === 401) {
                throw new RuntimeException('VDSina API authentication failed. Please check your API token.');
            }

            throw $e;
        } catch (Exception $e) {
            Log::error('VDSina API general error', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('VDSina API request failed: ' . $e->getMessage());
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

        Log::info('Creating VDSina server', $serverData);

        return $this->makeRequest('server', 'POST', $serverData);
    }

    /**
     * Удалить сервер
     */
    public function deleteServer(int $serverId): array
    {
        Log::info('Deleting VDSina server', [
            'server_id' => $serverId
        ]);

        return $this->makeRequest("server/{$serverId}", 'DELETE');
    }

    /**
     * Удалить все бэкапы
     */
    public function deleteAllBackups(int $serverId): array
    {
        Log::info('Deleting VDSina server backups', [
            'server_id' => $serverId
        ]);

        return $this->makeRequest("server.schedule/{$serverId}", 'DELETE');
    }

    /**
     * Перезагрузить сервер
     */
    public function rebootServer(int $serverId): array
    {
        Log::info('Rebooting VDSina server', [
            'server_id' => $serverId
        ]);

        return $this->makeRequest("server.reboot/{$serverId}", 'PUT');
    }

    /**
     * Выключить сервер
     */
    public function powerOffServer(int $serverId): array
    {
        Log::info('Powering off VDSina server', [
            'server_id' => $serverId
        ]);

        return $this->makeRequest("server.poweroff/{$serverId}", 'PUT');
    }

    /**
     * Включить сервер
     */
    public function powerOnServer(int $serverId): array
    {
        Log::info('Powering on VDSina server', [
            'server_id' => $serverId
        ]);

        return $this->makeRequest("server.poweron/{$serverId}", 'PUT');
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
