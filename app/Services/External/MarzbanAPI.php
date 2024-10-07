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

    //создание пользователя в панеле marzban
    public function createUser()
    {
        $action = 'user';

        $requestParam = [
            'headers' => [
                'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJhZG1pbiIsImFjY2VzcyI6InN1ZG8iLCJpYXQiOjE3MjYzMzA5NjgsImV4cCI6MTcyNjQxNzM2OH0.9L_J1AGWioylbepiGzIQ49QNntkMUYQi6WnpyDTGy3U',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => [
                'username' => 'testbott',
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
        return json_decode($result, true);
    }
}
