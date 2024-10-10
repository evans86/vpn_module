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
//        $this->apiKey = $apiKey;
    }

    //Получение токена для авторизации, время жизни токена - 1 день
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

    //создание пользователя в панеле marzban
    public function createUser(string $token, string $username)
    {
        $action = 'user';

        $requestParam = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => [
                'username' => $username,
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
        $result = (json_decode($result, true));

        dd($result);
        return $result;
    }
}
