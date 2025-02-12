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
                'verify' => false // Отключаем проверку SSL сертификата
            ];

            $client = new Client([
                'base_uri' => $this->host . '/api/',
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
    public function createUser(string $token, string $userId, int $data_limit, int $expire)
    {
        try {
            $action = 'user';

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => [
                    'username' => $userId,
                    'data_limit' => $data_limit, //лимит 25 гигов
                    'expire' => $expire, //время окончания через 30 дней
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
                        'vmess' => [
                            "VMESS HTTPUPGRADE NoTLS",
                        ],
                        'vless' => [
                            "VLESS HTTPUPGRADE NoTLS",
                        ],
//                        'trojan' => [
//                            "TROJAN WS NOTLS (WS)",
//                        ],
//                        'shadowsocks' => [
//                            "SHADOWSOCKS TCP (TCP)",
//                        ],
                    ],
                ],
                'verify' => false // Отключаем проверку SSL сертификата
            ];

            $client = new Client([
                'base_uri' => $this->host . '/api/',
                'verify' => false // Отключаем проверку SSL сертификата
            ]);

            $response = $client->post($action, $requestParam);

            Log::warning('response MARZBAN CREATE', [
                'response' => $response
            ]);

            $result = $response->getBody()->getContents();

            return (json_decode($result, true));
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @TODO метод на будущее
     *
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
    public function updateUser(string $token, string $userId, int $expire, int $data_limit)
    {
        try {
            $action = $userId;

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => [
                    'data_limit' => $data_limit, //лимит трафика
                    'expire' => $expire, //время окончания
                ],
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
//                    'Authorization' => 'Bearer ' . $token,
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

            Log::warning('STATS RESULT', [
                'base_uri' => $this->host . '/api/' . $action,
                'response' => $response,
                'panel_id' => $token
            ]);

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

            Log::warning('Last step MARZBAN DELETE', [
                'response' => 'Я тут '
            ]);

            $response = $client->delete($action, $requestParam);

            Log::warning('response MARZBAN DELETE НАконец-то дошли до удаления', [
                'response' => $response
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
     * @TODO не работает правльно
     *
     * Обновление данных администратора панели
     *
     * @param string $token
     * @param string $username
     * @param array $data
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    public function modifyAdmin(string $token, string $username, array $data)
    {
        try {
            $client = new Client([
                'base_uri' => rtrim($this->host, '/') . '/api/',
                'verify' => false
            ]);

            $requestParam = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => $data,
                'verify' => false
            ];

            $response = $client->put('admin/' . $username, $requestParam);
            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            throw new RuntimeException('Ошибка при обновлении данных администратора: ' . $e->getMessage());
        }
    }
}
