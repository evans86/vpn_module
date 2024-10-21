<?php

namespace App\Services\Panel\strategy;

use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelInterface;

class PanelMarzbanStrategy extends PanelMainStrategy implements PanelInterface
{
    /**
     * Создание панели
     *
     * @param int $server_id
     * @return void
     */
    public function create(int $server_id): void
    {
        $marzbanServer = new MarzbanService();
        $marzbanServer->create($server_id);
    }

    /**
     * Обновление конфигурации панели
     *
     * @param int $panel_id
     * @return void
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
     * @return void
     */
    public function addServerUser(int $panel_id): void
    {
        $marzbanServer = new MarzbanService();
        $marzbanServer->addServerUser($panel_id);
    }

    /**
     * Проверка использования пользователя
     *
     * @param int $panel_id
     * @param string $user_id
     * @return void
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
     */
    public function deleteServerUser(int $panel_id, string $user_id): void
    {
        $marzbanServer = new MarzbanService();
        $marzbanServer->deleteServerUser($panel_id, $user_id);
    }
}
