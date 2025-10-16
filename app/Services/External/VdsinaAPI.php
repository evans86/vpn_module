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
                    'Content-Type' => 'application/json', // Исправлено на JSON
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
                    // Для POST/PUT используем json
                    $options['json'] = $data;

                    // Дополнительная отладка
                    Log::debug('Request JSON data', [
                        'json' => json_encode($data, JSON_PRETTY_PRINT)
                    ]);
                }
            }

            Log::info('VDSina API Request', [
                'action' => $action,
                'method' => $method,
                'data' => $data
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

            // Детальное логирование для ошибок валидации
            if ($statusCode === 400) {
                Log::error('VDSina API Validation Error Details', [
                    'action' => $action,
                    'method' => $method,
                    'full_error' => $message, // Полное сообщение об ошибке
                    'data_sent' => $data
                ]);
            }

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

    public function updatePasswordWithRetry(int $serverId): array
    {
        Log::info('Updating VDSina server password with retry', [
            'server_id' => $serverId
        ]);

        $passwordAttempts = [
            // Попробуем простой пароль без спецсимволов
            'Simple123456',
            // Попробуем только буквы
            'MyServerPassword',
            // Попробуем с минимальной длиной
            'Pass1234',
            // Попробуем с дефисами
            'My-Password-123',
            // Попробуем с подчеркиваниями
            'My_Password_123',
            // Попробуем совсем простой
            'password123',
        ];

        foreach ($passwordAttempts as $password) {
            try {
                Log::info("Trying password: {$password}");

                $result = $this->makeRequest("server.password/{$serverId}", 'PUT', [
                    'password' => $password
                ]);

                Log::info("✅ Password accepted: {$password}");
                return $result;

            } catch (\Exception $e) {
                Log::warning("❌ Password rejected: {$password} - " . $e->getMessage());
                continue;
            }
        }

        throw new RuntimeException('All password attempts failed');
    }

    /**
     * Обновить пароль сервера
     */
    public function updatePassword(int $serverId, string $validPassword): array
    {
        Log::info('Updating VDSina server password', [
            'server_id' => $serverId
        ]);

        Log::info("Password to set: {$validPassword}");

        // Явно создаем JSON структуру как в документации
        $requestData = [
            'password' => $validPassword
        ];

        Log::info('Sending password update request', [
            'server_id' => $serverId,
            'data_structure' => $requestData
        ]);

        return $this->makeRequest("server.password/{$serverId}", 'PUT', $requestData);
    }

    /**
     * Генерация пароля, соответствующего требованиям VDSina
     * Требования: минимум 8 символов, буквы + цифры + специальные символы
     */
    public function generateVdsinaPassword(): string
    {
        // VDSina требует пароль для суперпользователя
        // Создаем надежный пароль с буквами, цифрами и длиной 12-16 символов
        $length = random_int(12, 16);
        $chars = [
            'abcdefghijklmnopqrstuvwxyz',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            '0123456789',
            '!@#$%^&*'
        ];

        $password = '';

        // Гарантируем наличие хотя бы одного символа из каждой группы
        foreach ($chars as $charGroup) {
            $password .= $charGroup[random_int(0, strlen($charGroup) - 1)];
        }

        // Добавляем остальные символы
        $allChars = implode('', $chars);
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Перемешиваем пароль
        $password = str_shuffle($password);

        return $password;
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
