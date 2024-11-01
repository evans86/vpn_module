<?php

namespace App\Services\Server;

use App\Models\Server\Server;
use App\Services\Server\strategy\ServerVdsinaStrategy;
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
     * Создание сервера в первоначальном виде
     *
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function configure(int $location_id, string $provider, bool $isFree): void
    {
        $this->strategy->configure($location_id, $provider, $isFree);
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
     */
    public function setPanel(int $server_id, string $panel): void
    {
        $this->strategy->setPanel($server_id, $panel);
    }

    /**
     * Удаление сервера
     *
     * @param int $server_id
     * @return void
     */
    public function delete(int $server_id): void
    {
        $this->strategy->delete($server_id);
    }
}
