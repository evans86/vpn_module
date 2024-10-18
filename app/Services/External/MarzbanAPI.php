<?php

namespace App\Services\External;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class MarzbanAPI
{
    private $host;

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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getToken(string $username, string $password)
    {
        $action = 'admin/token';

        $requestParam = [
            'form_params' => [
                'username' => $username,
                'password' => $password,
            ],
        ];

        $client = new Client(['base_uri' => $this->host . '/api/']);

        $response = $client->post($action, $requestParam);
        $result = $response->getBody()->getContents();
        $result = (json_decode($result, true));

        return $result['access_token'];
    }

    /**
     * Обновление конфига панели
     *
     * @param $token
     * @param $json_config
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function modifyConfig($token, $json_config)
    {
        $action = 'core/config';

        $requestParam = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => $json_config
        ];

        $client = new Client(['base_uri' => $this->host . '/api/']);

        $response = $client->put($action, $requestParam);
        $result = $response->getBody()->getContents();
        $result = (json_decode($result, true));

//        dd($result);
        return $result;
    }

    /**
     * Создание пользователя в панели marzban
     *
     * @param string $token
     * @param string $userId
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createUser(string $token, string $userId)
    {
        $action = 'user';

        $requestParam = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => [
                'username' => $userId,
                'proxies' => [
                    "vmess" => [

                    ],
                    'vless' => [

                    ],
                    'trojan' => [

                    ],
                    'shadowsocks' => [

                    ]
                ]
            ],
        ];

        $client = new Client(['base_uri' => $this->host . '/api/']);
        $response = $client->post($action, $requestParam);

        $result = $response->getBody()->getContents();

        return (json_decode($result, true));
    }

    /**
     * Удаление пользователя в панели marzban
     *
     * @param string $token
     * @param string $userId
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteUser(string $token, string $userId)
    {
        $action = $userId;

        $requestParam = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ];

        $client = new Client(['base_uri' => $this->host . '/api/user/']);
        $response = $client->delete($action, $requestParam);

        $result = $response->getBody()->getContents();

        return (json_decode($result, true));
    }
}
