<?php

namespace App\Services\External;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class VdsinaAPI
{
    const HOST_RU = 'https://userapi.vdsina.ru/v1/';
    const HOST_COM = 'https://userapi.vdsina.com/v1/';
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    //тестовый на данный аккаунта
    public function getAccount()
    {
        try {
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

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);

            $result = $response->getBody()->getContents();
//        dd($result);
            return json_decode($result, true);
        } catch (\RuntimeException $r) {
            //запись в лог
            throw new \RuntimeException('error');
        }
    }

    //интересут id = 2 Standard servers
    public function getServerGroup()
    {
        try {
            $action = 'server-group';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);

            $result = $response->getBody()->getContents();
//        dd(json_decode($result));
            return json_decode($result, true);
        } catch (\RuntimeException $r) {
            //запись в лог
            throw new \RuntimeException('error');
        }
    }

    //Возвращается список дата-центров
    //id = 1 - Amsterdam
    public function getDatacenter()
    {
        try {
            $action = 'datacenter';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);

            $result = $response->getBody()->getContents();
//        dd(json_decode($result));
            return json_decode($result, true);
        } catch (\RuntimeException $r) {
            //запись в лог
            throw new \RuntimeException('error');
        }
    }

    //Список шаблонов операционных систем, доступных для установки или переустановки сервера
    //Интересует id = 23, Ubuntu 24.04
    public function getTemplate()
    {
        try {
            $action = 'template';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);

            $result = $response->getBody()->getContents();
//        dd(json_decode($result));
            return json_decode($result, true);
        } catch (\RuntimeException $r) {
            //запись в лог
            throw new \RuntimeException('error');
        }
    }

    //Список тарифных планов, доступен по ID группы сервера
    //Интересует Standard Server id = 2
    //Из полученного списка тарифных планов выбираем по индексу 0 с id = 1
    public function getServerPlan()
    {
        try {
            $action = 'server-plan/2';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);

            $result = $response->getBody()->getContents();
//        dd(json_decode($result));
            return json_decode($result, true);
        } catch (\RuntimeException $r) {
            //запись в лог
            throw new \RuntimeException('error');
        }
    }

    //создание сервера на vdsina
    //Ответ:
    //{#307 ▼
    //  +"status": "ok"
    //  +"status_msg": "Server created"
    //  +"data": {#305 ▼
    //    +"id": 138828
    //  }
    //}
    /**
     * @param string $server_name //имя сервера
     * @param int $server_plan //id = 1 - базовый тарифный план
     * @param int $autoprolong //0 - без авто продления, 1 - авто продление
     * @param int $datacenter //id = 1 - Amsterdam
     * @param int $template //id = 23 - Ubuntu 24.04
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createServer(
        string $server_name,
        int    $server_plan,
        int    $autoprolong = 0,
        int    $datacenter = 1,
        int    $template = 23
    )
    {
//        try {
        $action = 'server';

        $requestParam = [
            'headers' => [
                'Authorization' => $this->apiKey,
            ],
            'json' => [
                'name' => $server_name,
                'autoprolong' => $autoprolong,
                'datacenter' => $datacenter,
                'server-plan' => $server_plan,
                'template' => $template
            ],
        ];

        $client = new Client(['base_uri' => self::HOST_COM]);
        $response = $client->post($action, $requestParam);

        $result = $response->getBody()->getContents();
//          dd(json_decode($result));
        return json_decode($result, true);
//        } catch (\RuntimeException $r) {
//            //запись в лог ТГ
//            throw new \RuntimeException('error create server');
//        }
    }

    //получить список серверов
    public function getServers()
    {
        try {
            $action = 'server';

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]

            ];

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);

            $result = $response->getBody()->getContents();
//          dd(json_decode($result));
            return json_decode($result, true);
        } catch (\RuntimeException $r) {
            //запись в лог
            throw new \RuntimeException('error');
        }
    }

    public function updatePassword(int $provider_id, string $password)
    {
//        try {
            $action = 'server.password/'. $provider_id;

            $requestParam = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ],
                'json' => [
                    'password' => $password
                ],
            ];

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->put($action, $requestParam);

            $result = $response->getBody()->getContents();
//          dd(json_decode($result));
            return json_decode($result, true);
//        } catch (\RuntimeException $r) {
//            //запись в лог
//            throw new \RuntimeException('error');
//        }
    }

    //получить сервер по id

    /**
     * @param $provider_id
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getServerById(int $provider_id)
    {
        try {
            $action = 'server/' . $provider_id;

            $headers = [
                'headers' => [
                    'Authorization' => $this->apiKey,
                ]
            ];

            $client = new Client(['base_uri' => self::HOST_COM]);
            $response = $client->get($action, $headers);

            $result = $response->getBody()->getContents();
//            dd(json_decode($result));
            return json_decode($result, true);
        } catch (\RuntimeException $r) {
            //запись в лог
            throw new \RuntimeException('error');
        }
    }
}
