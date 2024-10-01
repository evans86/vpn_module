<?php

namespace App\Services\External;

use Cloudflare\API\Adapter\Guzzle;

class CloudflareAPI
{
    //Возвращает адаптер для работы с API
    public static function getAdapter(): Guzzle
    {
        //вынести в .env
        $email = 'support@bot-t.ru';
        $api_key = '1697f393d7d2fceb7866b0c7062d025b8cfe6';
        $key = new \Cloudflare\API\Auth\APIKey($email, $api_key); //подключение к API
        $adapter = new \Cloudflare\API\Adapter\Guzzle($key); //адаптер

        return $adapter;
    }

    //создать dns запись
    public function createDNSRecord(string $name, string $ip)
    {
        $DNSRecord = new \Cloudflare\API\Endpoints\DNS(self::getAdapter());

        $zone_id = 'ecd4115fa760df3dd0a5f9c0e2caee2d'; //zone_id
        $type = 'A'; //type
//        $name = 'server_test'; //name
//        $content = '89.110.107.231'; //content

        $dns = $DNSRecord->addRecord($zone_id, $type, $name, $ip); //добавление DNS-записи

        return $dns;
    }

    //получить id последней созданной записи
    public function getRecordID()
    {
        $DNSRecord = new \Cloudflare\API\Endpoints\DNS(self::getAdapter());
        $zone_id = 'ecd4115fa760df3dd0a5f9c0e2caee2d'; //zone_id

        $dnsRecordId = $DNSRecord->getRecordID($zone_id); //получение DNS-записей

        dd($dnsRecordId);
        return $dnsRecordId;
    }

    //получить подробную информацию о DNS-записях в зоне
    public function getRecords()
    {
        $DNSRecord = new \Cloudflare\API\Endpoints\DNS(self::getAdapter());

        $zone_id = 'ecd4115fa760df3dd0a5f9c0e2caee2d'; //zone_id
        $dnsList = $DNSRecord->listRecords($zone_id); //получение DNS-записей

        dd($dnsList);
        return $dnsList;
    }

    //удалить dns запись
    public function deleteRecord($id)
    {
        $DNSRecord = new \Cloudflare\API\Endpoints\DNS(self::getAdapter());

        $zone_id = 'ecd4115fa760df3dd0a5f9c0e2caee2d'; //zone_id
        $record_id = '1'; //id DNS-записи

        $record = $DNSRecord->deleteRecord($zone_id, $record_id); //удаление DNS-записи

        return $record;
    }


}
