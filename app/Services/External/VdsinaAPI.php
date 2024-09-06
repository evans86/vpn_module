<?php

namespace App\Services\External;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class VdsinaAPI
{
    const HOST = 'https://userapi.vdsina.ru/v1/';
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    //тестовый на данный аккаунта
    public function getAccount()
    {
//        try {
        $action = 'account';

        $requestParam = [
            RequestOptions::JSON => [
//                'email' => 'support@vpn-telegram.com',
//                'password' => 'QScM69NuRrVDEbsjR37G'
            ]
        ];
        $headers = [
            'headers' => [
                'Authorization' => $this->apiKey,
            ]
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->get($action, $headers);

        $result = $response->getBody()->getContents();
        dd($result);
        return json_decode($result, true);
//        } catch (\RuntimeException $r) {
//            //запись в лог
//            throw new \RuntimeException('error');
//        }
    }

    //интересут id = 1 Standard servers
    public function getServerGroup()
    {
        $action = 'server-group';

        $headers = [
            'headers' => [
                'Authorization' => $this->apiKey,
            ]
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->get($action, $headers);

        $result = $response->getBody()->getContents();
        dd(json_decode($result));
        return json_decode($result, true);
    }

    //Возвращается список дата-центров
    //id = 3 - Москва
    //id = 4 - Amsterdam
    public function getDatacenter()
    {
        $action = 'datacenter';

        $headers = [
            'headers' => [
                'Authorization' => $this->apiKey,
            ]
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->get($action, $headers);

        $result = $response->getBody()->getContents();
        dd(json_decode($result));
        return json_decode($result, true);
    }

    //Список шаблонов операционных систем, доступных для установки или переустановки сервера
    //Интересует id = 48, Ubuntu 24.0
    public function getTemplate()
    {
        $action = 'template';

        $headers = [
            'headers' => [
                'Authorization' => $this->apiKey,
            ]
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->get($action, $headers);

        $result = $response->getBody()->getContents();
        dd(json_decode($result));
        return json_decode($result, true);
    }

    //Список тарифных планов, доступен по ID группы сервера
    //Интересует Standard Server id = 1
    //Из полученного списка тарифных планов выбираем по индексу 1 с id = 114
    public function getServerPlan()
    {
        $action = 'server-plan/0';

        $headers = [
            'headers' => [
                'Authorization' => $this->apiKey,
            ]
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->get($action, $headers);

        $result = $response->getBody()->getContents();
        dd(json_decode($result));
        return json_decode($result, true);
    }

    //создание сервера на vdsina
    public function createServer()
    {
        $action = 'server';

        $requestParam = [
            'name' => 'test_server',
            'autoprolong' => 0,
            'datacenter' => 4,
            'server-plan' => 114,
            'template' => 48,
            'iso' => 1
        ];

        $headers = [
            'headers' => [
                'Authorization' => $this->apiKey,
            ],
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->post($action . '?' . http_build_query($requestParam), $headers);

        $result = $response->getBody()->getContents();
        dd(json_decode($result));
        return json_decode($result, true);
    }
}
