<?php

namespace App\Services\Panel\strategy;

use App\Models\ServerUser\ServerUser;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class PanelMarzbanStrategy extends PanelMainStrategy implements PanelInterface
{
    private MarzbanService $marzbanService;

    public function __construct(MarzbanService $marzbanService)
    {
        $this->marzbanService = $marzbanService;
    }

    /**
     * Создание панели
     *
     * @param int $server_id
     * @return void
     * @throws Exception
     */
    public function create(int $server_id): void
    {
        $this->marzbanService->create($server_id);
    }

    /**
     * @throws GuzzleException
     */
    public function getSubscribeInfo(int $panel_id, string $user_id): array
    {
        return $this->marzbanService->getUserSubscribeInfo($panel_id, $user_id);
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    public function getServerStats(): void
    {
        $this->marzbanService->getServerStats();
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
        $this->marzbanService->updateConfiguration($panel_id);
    }

    /**
     * Обновление конфигурации панели - стабильный вариант (без REALITY)
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfigurationStable(int $panel_id): void
    {
        $this->marzbanService->updateConfigurationStable($panel_id);
    }

    /**
     * Обновление конфигурации панели - с REALITY (лучший обход блокировок)
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfigurationReality(int $panel_id): void
    {
        $this->marzbanService->updateConfigurationReality($panel_id);
    }

    /**
     * Добавление пользователя
     *
     * @param int $panel_id
     * @param int $userTgId
     * @param int $data_limit
     * @param int $expire
     * @param string $key_activate_id
     * @return ServerUser
     * @throws GuzzleException
     */
    public function addServerUser(int $panel_id, int $userTgId, int $data_limit, int $expire, string $key_activate_id, array $options): ServerUser
    {
        return $this->marzbanService->addServerUser($panel_id, $userTgId, $data_limit, $expire, $key_activate_id, $options);
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
        $this->marzbanService->checkOnline($panel_id, $user_id);
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
        $this->marzbanService->deleteServerUser($panel_id, $user_id);
    }

    /**
     * Обновление токена авторизации панели
     *
     * @param int $panel_id ID панели
     * @return \App\Models\Panel\Panel Обновленная панель
     * @throws \Exception
     */
    public function updateToken(int $panel_id): \App\Models\Panel\Panel
    {
        return $this->marzbanService->updateMarzbanToken($panel_id);
    }

    /**
     * Перенос пользователя с одной панели на другую
     *
     * @param int $sourcePanel_id ID исходной панели
     * @param int $targetPanel_id ID целевой панели
     * @param string $serverUser_id ID пользователя сервера (key_activate_id)
     * @return \App\Models\ServerUser\ServerUser Обновленный пользователь сервера
     * @throws \Exception
     */
    public function transferUser(int $sourcePanel_id, int $targetPanel_id, string $serverUser_id): \App\Models\ServerUser\ServerUser
    {
        return $this->marzbanService->transferUser($sourcePanel_id, $targetPanel_id, $serverUser_id);
    }
}
