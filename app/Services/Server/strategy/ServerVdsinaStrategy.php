<?php

namespace App\Services\Server\strategy;

use App\Models\Server\Server;
use App\Services\Panel\PanelStrategy;
use App\Services\Server\vdsina\VdsinaService;
use GuzzleHttp\Exception\GuzzleException;

class ServerVdsinaStrategy extends ServerMainStrategy
{
    private VdsinaService $service;

    public function __construct()
    {
        $this->service = new VdsinaService();
    }

    /**
     * Первоначальное создание сервера
     *
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return Server
     * @throws GuzzleException
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        return $this->service->configure($location_id, $provider, $isFree);
    }

    /**
     * Проверка статуса сервера и окончательная настройка
     *
     * @return void
     * @throws GuzzleException
     */
    public function checkStatus(): void
    {
        $this->service->checkStatus();
    }

    /**
     * Добавление панели к серверу
     *
     * @param int $server_id
     * @param string $panel
     * @return void
     */
    public function setPanel(int $server_id, string $panel): void
    {
        $server = Server::query()->where('id', $server_id)->first();

        $panelStrategy = new PanelStrategy($panel);
        $panelStrategy->create($server->id);
    }

    /**
     * Удаление сервера
     *
     * @param int $server_id
     * @return void
     * @throws GuzzleException
     */
    public function delete(int $server_id): void
    {
        $this->service->delete($server_id);
    }
}
