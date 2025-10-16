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
     * Базовый метод для выполнения запросов с разными методами аутентификации
     */
    private function makeRequest(string $action, string $method = 'GET', array $data = []): array
    {
        $authMethods = [
            'bearer_token' => ['Authorization' => 'Bearer ' . $this->apiKey],
            'token_only' => ['Authorization' => $this->apiKey],
            'x_api_key' => ['X-API-Key' => $this->apiKey],
            'api_key_header' => ['API-Key' => $this->apiKey],
            'bearer_lowercase' => ['authorization' => 'bearer ' . $this->apiKey],
        ];

        $lastError = null;

        foreach ($authMethods as $authName => $authHeaders) {
            try {
                Log::info("Trying authentication method: {$authName}", ['headers' => array_keys($authHeaders)]);

                $options = [
                    'headers' => array_merge([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'User-Agent' => 'VDSina-Client/1.0',
                    ], $authHeaders),
                    'timeout' => 30,
                    'connect_timeout' => 10,
                ];

                // Для разных методов используем разные форматы данных
                if (!empty($data)) {
                    if ($method === 'GET') {
                        $options['query'] = $data;
                    } else {
                        $options['form_params'] = $data;
                    }
                }

                $client = new Client(['base_uri' => self::BASE_URL]);
                $response = $client->request($method, $action, $options);

                $result = $response->getBody()->getContents();
                $data = json_decode($result, true);

                if (!is_array($data)) {
                    throw new RuntimeException('Invalid JSON response from VDSina');
                }

                if (!isset($data['status']) || $data['status'] !== 'ok') {
                    // Если это ошибка аутентификации, пробуем следующий метод
                    if (isset($data['status_code']) && $data['status_code'] === 401) {
                        $lastError = "Auth method '{$authName}' failed: " . ($data['status_msg'] ?? 'Unauthorized');
                        Log::warning($lastError);
                        continue; // Пробуем следующий метод аутентификации
                    }

                    throw new RuntimeException('VDSina API error: ' . ($data['status_msg'] ?? 'Unknown error'));
                }

                Log::info("✅ Authentication successful with method: {$authName}");
                Log::info('VDSina API Response Success', [
                    'action' => $action,
                    'auth_method' => $authName,
                    'data_count' => isset($data['data']) ? (is_array($data['data']) ? count($data['data']) : 'single') : 'none'
                ]);

                return $data;

            } catch (GuzzleException $e) {
                $statusCode = $e->getCode();

                // Если это 401 ошибка, пробуем следующий метод аутентификации
                if ($statusCode === 401) {
                    $lastError = "Auth method '{$authName}' failed with 401: " . $e->getMessage();
                    Log::warning($lastError);
                    continue;
                }

                // Другие ошибки - сразу выбрасываем исключение
                throw $e;
            }
        }

        // Если все методы аутентификации провалились
        throw new RuntimeException('All authentication methods failed. Last error: ' . $lastError);
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
     * Обновить пароль сервера
     */
    public function updatePassword(int $serverId, string $password): array
    {
        Log::info('Updating VDSina server password', [
            'server_id' => $serverId
        ]);

        return $this->makeRequest("server.password/{$serverId}", 'PUT', [
            'password' => $password
        ]);
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
                    'account' => $result['data']['email'] ?? 'Unknown'
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

    /**
     * Прямой тест аутентификации с разными методами
     */
    public function testAuthMethods(): array
    {
        $methods = [
            'Bearer Token' => ['Authorization' => 'Bearer ' . $this->apiKey],
            'Token Only' => ['Authorization' => $this->apiKey],
            'X-API-Key' => ['X-API-Key' => $this->apiKey],
            'API-Key' => ['API-Key' => $this->apiKey],
            'Bearer lowercase' => ['authorization' => 'bearer ' . $this->apiKey],
        ];

        $results = [];

        foreach ($methods as $methodName => $headers) {
            try {
                $client = new Client(['base_uri' => self::BASE_URL, 'timeout' => 10]);

                $response = $client->get('account', [
                    'headers' => array_merge([
                        'Accept' => 'application/json',
                        'User-Agent' => 'VDSina-Client/1.0',
                    ], $headers),
                ]);

                $result = $response->getBody()->getContents();
                $data = json_decode($result, true);

                $results[$methodName] = [
                    'success' => isset($data['status']) && $data['status'] === 'ok',
                    'status' => $data['status'] ?? 'error',
                    'status_msg' => $data['status_msg'] ?? null,
                    'status_code' => $data['status_code'] ?? $response->getStatusCode(),
                ];

            } catch (\Exception $e) {
                $results[$methodName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ];
            }
        }

        return $results;
    }
}
