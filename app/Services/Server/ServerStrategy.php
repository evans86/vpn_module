<?php

namespace App\Services\Server;

use App\Models\Server\Server;
use App\Services\Server\strategy\ServerVdsinaStrategy;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class ServerStrategy
{
    public ServerInterface $strategy;

    public function __construct(string $provider)
    {
        switch ($provider) {
            case Server::VDSINA:
                $this->strategy = new ServerVdsinaStrategy();
                break;
            default:
                throw new \DomainException('Server strategy not found');
        }
    }

    /**
     * @param Server $server
     * @return bool
     */
    public function ping(Server $server): bool
    {
        $this->strategy->ping($server);
    }

    /**
     * Создание сервера в первоначальном виде
     *
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return Server
     * @throws GuzzleException
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        return $this->strategy->configure($location_id, $provider, $isFree);
    }

    /**
     * Проверка статуса сервера с окончательной настройкой сервера
     *
     * @return void
     * @throws GuzzleException
     */
    public function checkStatus(): void
    {
        $this->strategy->checkStatus();
    }

    /**
     * Создание панели и привязка к текущему серверу
     *
     * @param int $server_id
     * @param string $panel
     * @return void
     * @throws Exception
     */
    public function setPanel(int $server_id, string $panel): void
    {
        $this->strategy->setPanel($server_id, $panel);
    }

    /**
     * Удаление сервера
     *
     * @param Server $server
     * @return void
     * @throws Exception
     */
    public function delete(Server $server): void
    {
        $this->strategy->delete($server);
    }
}
