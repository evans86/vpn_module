<?php

namespace App\Services\Server\strategy;

use App\Models\Server\Server;
use App\Services\Server\ServerInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

abstract class ServerMainStrategy implements ServerInterface
{
    /**
     * Проверка статуса сервера и окончательная настройка
     *
     * @return void
     * @throws GuzzleException
     */
    abstract public function checkStatus(): void;

    /**
     * Первоначальное создание сервера
     *
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return Server
     * @throws GuzzleException
     */
    abstract public function configure(int $location_id, string $provider, bool $isFree): Server;

    /**
     * Добавление панели к серверу
     *
     * @param int $server_id
     * @param string $panel
     * @return void
     */
    abstract public function setPanel(int $server_id, string $panel): void;

    /**
     * Удаление сервера
     *
     * @param Server $server
     * @return void
     * @throws \Exception
     */
    public function delete(Server $server): void
    {
        throw new RuntimeException('Method not implemented');
    }
}
