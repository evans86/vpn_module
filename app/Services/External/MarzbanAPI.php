<?php

namespace App\Services\External;

use GuzzleHttp\Client;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MarzbanAPI
{
    private string $host;

    public function __construct($host)
    {
        $this->host = $host;
    }

    /**
     * Получение токена для авторизации, время жизни токена - 1 день
     *
     * @param string $username
     * @param string $password
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function getToken(string $username, string $password)
    {
        try {
            $host = rtrim($this->host, '/');

            $client = new Client([
                'base_uri' => $host,
                'verify' => false
            ]);

            $response = $client->post('api/admin/token', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'grant_type' => 'password',
                    'username' => $username,
                    'password' => $password,
                    'scope' => '',
                ],
                'verify' => false
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['access_token'])) {
                throw new RuntimeException('Не удалось получить токен доступа');
            }

            return $result['access_token'];
        } catch (Exception $e) {
            throw new RuntimeException('Ошибка при получении токена: ' . $e->getMessage());
        }
    }

    /**
     * Обновление конфига панели
     *
     * @param $token
     * @param $json_config
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function modifyConfig($token, $json_config)
    {
        try {
            $action = 'core/config';

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => $json_config,
                'verify' => false, // Отключаем проверку SSL сертификата
                'timeout' => 30, // Таймаут 30 секунд
                'connect_timeout' => 10 // Таймаут подключения 10 секунд
            ];

            $client = new Client([
                'base_uri' => $this->host . '/api/',
                'verify' => false, // Отключаем проверку SSL сертификата
                'timeout' => 30,
                'connect_timeout' => 10
            ]);

            $response = $client->put($action, $requestParam);
            $result = $response->getBody()->getContents();
            return (json_decode($result, true));
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            // Обработка ошибок 5xx (500, 502, 503, 504 и т.д.)
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            $responseBody = '';
            
            try {
                if ($e->getResponse()) {
                    $responseBody = $e->getResponse()->getBody()->getContents();
                }
            } catch (\Exception $bodyException) {
                $responseBody = 'Не удалось прочитать тело ответа';
            }
            
            $message = "Сервер Marzban недоступен (HTTP {$statusCode}). ";
            
            if ($statusCode === 500) {
                $message .= "Внутренняя ошибка сервера. Возможные причины: невалидная конфигурация, ошибка в xray-core, или проблемы с сервером.";
                if (!empty($responseBody)) {
                    $message .= " Ответ сервера: " . substr($responseBody, 0, 200);
                }
            } elseif ($statusCode === 502) {
                $message .= "Возможные причины: сервер перегружен, контейнер Marzban не запущен, или проблемы с сетью.";
            } elseif ($statusCode === 503) {
                $message .= "Сервис временно недоступен. Попробуйте позже.";
            } elseif ($statusCode === 504) {
                $message .= "Таймаут запроса. Сервер не отвечает вовремя.";
            }
            
            Log::error('Marzban server error', [
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'response_body' => $responseBody,
                'host' => $this->host,
                'source' => 'api'
            ]);
            
            throw new RuntimeException($message);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Обработка ошибок 4xx (401, 403, 404 и т.д.)
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            $message = "Ошибка авторизации или доступа (HTTP {$statusCode}). ";
            
            if ($statusCode === 401) {
                $message .= "Неверный токен авторизации. Обновите токен.";
            } elseif ($statusCode === 403) {
                $message .= "Доступ запрещен. Проверьте права доступа.";
            } elseif ($statusCode === 404) {
                $message .= "Эндпоинт не найден. Проверьте версию Marzban.";
            }
            
            Log::error('Marzban client error', [
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'host' => $this->host,
                'source' => 'api'
            ]);
            
            throw new RuntimeException($message);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Обработка ошибок подключения
            $message = "Не удалось подключиться к серверу Marzban. ";
            $message .= "Проверьте доступность сервера и правильность адреса: " . $this->host;
            
            Log::error('Marzban connection error', [
                'error' => $e->getMessage(),
                'host' => $this->host,
                'source' => 'api'
            ]);
            
            throw new RuntimeException($message);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            Log::error('Marzban config update error', [
                'error' => $e->getMessage(),
                'host' => $this->host,
                'source' => 'api'
            ]);
            throw new Exception('Ошибка при обновлении конфигурации: ' . $e->getMessage());
        }
    }

    /**
     * Создание пользователя в панели marzban с трафиком 25 GB и датой окончания через 30 дней
     *
     * @param string $token
     * @param string $userId
     * @param int $data_limit
     * @param int $expire
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function createUser(string $token, string $userId, int $data_limit, int $expire, ?int $maxConnections = null)
    {
        try {
            $action = 'user';

            // Используем значение из конфига, если не передано
            if ($maxConnections === null) {
                $maxConnections = config('panel.max_connections', 4);
            }

            // Определяем level: если max_connections <= 3, то level = 0, иначе level = 1
            $level = $maxConnections <= 3 ? 0 : 1;

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => [
                    'username' => $userId,
                    'data_limit' => $data_limit,
                    'expire' => $expire,
                    'proxies' => [
                        "vmess" => [
                            "id" => Str::uuid()->toString()
                        ],
                        'vless' => [
                            "id" => Str::uuid()->toString()
                        ],
                        'trojan' => [
                            'password' => substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16)
                        ],
                        'shadowsocks' => [
                            'password' => substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16)
                        ]
                    ],
                    'inbounds' => [
                        'vmess' => ["VMESS-WS"],
                        'vless' => ["VLESS-WS"],
                        'trojan' => ["TROJAN-WS"],
                        'shadowsocks' => ["Shadowsocks-TCP"],
                    ],
                    'level' => $level // ← ДОБАВЛЯЕМ УРОВЕНЬ ПОЛИТИКИ
                ],
                'verify' => false
            ];

            $client = new Client([
                'base_uri' => $this->host . '/api/',
                'verify' => false
            ]);

            $response = $client->post($action, $requestParam);
            $result = $response->getBody()->getContents();

            return json_decode($result, true);
        } catch (Exception $e) {
            Log::error('Failed to create user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'source' => 'api'
            ]);
            throw new Exception('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * @TODO метод на будущее
     *
     * Обновление у пользователя даты окончания и лимита трафика
     *
     * @param string $token
     * @param string $userId
     * @param int $expire
     * @param int $data_limit
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function updateUser(string $token, string $userId, int $expire, int $data_limit, ?array $inbounds = null)
    {
        try {
            $action = $userId;

            $jsonData = [
                'data_limit' => $data_limit, //лимит трафика
                'expire' => $expire, //время окончания
            ];

            // Если переданы inbounds, добавляем их в запрос
            if ($inbounds !== null) {
                $jsonData['inbounds'] = $inbounds;
            }

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => $jsonData,
                'verify' => false // Отключаем проверку SSL сертификата
            ];

            $client = new Client([
                'base_uri' => $this->host . '/api/user/',
                'verify' => false // Отключаем проверку SSL сертификата
            ]);

            $response = $client->put($action, $requestParam);

            $result = $response->getBody()->getContents();

            return (json_decode($result, true));
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Получение пользователя
     *
     * @param string $token
     * @param string $userId
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function getUser(string $token, string $userId)
    {
        try {
            $action = $userId;

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'verify' => false // Отключаем проверку SSL сертификата
            ];

            $client = new Client([
                'base_uri' => $this->host . '/api/user/',
                'verify' => false // Отключаем проверку SSL сертификата
            ]);

            $response = $client->get($action, $requestParam);

            $result = $response->getBody()->getContents();

            return (json_decode($result, true));
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Статистика панели (сервера)
     *
     * @param string $token
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function getServerStats(string $token)
    {
        try {
            $action = 'system';

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'verify' => false // Отключаем проверку SSL сертификата
            ];

            $client = new Client([
                'base_uri' => $this->host . '/api/',
                'verify' => false // Отключаем проверку SSL сертификата
            ]);

            $response = $client->get($action, $requestParam);

            $result = $response->getBody()->getContents();
            return (json_decode($result, true));
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Удаление пользователя в панели marzban
     *
     * @param string $token
     * @param string $userId
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function deleteUser(string $token, string $userId)
    {
        try {
            $action = $userId;

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'verify' => false // Отключаем проверку SSL сертификата
            ];

            $client = new Client([
                'base_uri' => $this->host . '/api/user/',
                'verify' => false // Отключаем проверку SSL сертификата
            ]);

            $response = $client->delete($action, $requestParam);

            // User deletion completed
            Log::info('User deletion completed', [
                'response' => $response,
                'source' => 'api'
            ]);

            $result = $response->getBody()->getContents();

            return (json_decode($result, true));
        } catch (RuntimeException $r) {
            $result = 'Пока костыль потому что 500';
            return (json_decode($result, true));
//            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            $result = 'Пока костыль потому что 500';
            return (json_decode($result, true));
//            throw new Exception($e->getMessage());
        }
    }

    /**
     * Получение текущей конфигурации
     */
    public function getConfig(string $token): array
    {
        try {
            $client = new Client([
                'base_uri' => $this->host . '/api/',
                'verify' => false
            ]);

            $response = $client->get('core/config', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return $result ?? [];

        } catch (\Exception $e) {
            Log::error('Failed to get Marzban config', [
                'error' => $e->getMessage(),
                'source' => 'api'
            ]);
            return [];
        }
    }

    /**
     * Обновление конфигурации
     */
    public function updateConfig(string $token, array $config): array
    {
        try {
            $client = new Client([
                'base_uri' => $this->host . '/api/',
                'verify' => false
            ]);

            $response = $client->put('core/config', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                'json' => $config
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (\Exception $e) {
            Log::error('Failed to update Marzban config', [
                'error' => $e->getMessage(),
                'source' => 'api'
            ]);
            throw $e;
        }
    }
}
