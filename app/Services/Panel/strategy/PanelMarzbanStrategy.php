<?php

namespace App\Services\Panel\strategy;

use App\Models\ServerUser\ServerUser;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class PanelMarzbanStrategy extends PanelMainStrategy implements PanelInterface
{
    /**
     * Создание панели
     *
     * @param int $server_id
     * @return void
     * @throws Exception
     */
    public function create(int $server_id): void
    {
        $marzbanServer = new MarzbanService();
        $marzbanServer->create($server_id);
    }

    /**
     * @throws GuzzleException
     */
    public function getSubscribeInfo(int $panel_id, string $user_id): array
    {
        $marzbanServer = new MarzbanService();
        return $marzbanServer->getUserSubscribeInfo($panel_id, $user_id);
    }

    /**
     * Обновление конфигурации панели
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfiguration(int $panel_id): void
    {
        $marzbanServer = new MarzbanService();
        $marzbanServer->updateConfiguration($panel_id);
    }

    /**
     * Добавление пользователя
     *
     * @param int $panel_id
     * @param int $data_limit
     * @param int $expire
     * @param string $key_activate_id
     * @return ServerUser
     * @throws GuzzleException
     */
    public function addServerUser(int $panel_id, int $data_limit, int $expire, string $key_activate_id): ServerUser
    {
        $marzbanServer = new MarzbanService();
        return $marzbanServer->addServerUser($panel_id, $data_limit, $expire, $key_activate_id);
    }

    /**
     * Проверка использования пользователя
     *
     * @param int $panel_id
     * @param string $user_id
     * @return void
     * @throws GuzzleException
     */
    public function checkOnline(int $panel_id, string $user_id): void
    {
        $marzbanServer = new MarzbanService();
        $marzbanServer->checkOnline($panel_id, $user_id);
    }

    /**
     * Удаление пользователя панели
     *
     * @param int $panel_id
     * @param string $user_id
     * @return void
     * @throws GuzzleException
     */
    public function deleteServerUser(int $panel_id, string $user_id): void
    {
        $marzbanServer = new MarzbanService();
        $marzbanServer->deleteServerUser($panel_id, $user_id);
    }
}
