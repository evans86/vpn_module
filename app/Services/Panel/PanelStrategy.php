<?php

namespace App\Services\Panel;

use App\Models\Panel\Panel;
use App\Models\ServerUser\ServerUser;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Strategy pattern для работы с панелями различных типов
 * 
 * Использует фабрику для создания стратегий, что позволяет легко добавлять новые типы панелей
 * 
 * @deprecated Используйте PanelStrategyFactory напрямую для создания стратегий
 * Этот класс сохранен для обратной совместимости
 */
class PanelStrategy
{
    public PanelInterface $strategy;
    private PanelStrategyFactory $factory;

    public function __construct(string $provider)
    {
        $this->factory = new PanelStrategyFactory();
        $this->strategy = $this->factory->create($provider);
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
        $this->strategy->create($server_id);
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    public function getServerStats(): void
    {
        $this->strategy->getServerStats();
    }

    /**
     * @param int $panel_id
     * @param string $user_id
     * @return array
     * @throws GuzzleException
     */
    public function getSubscribeInfo(int $panel_id, string $user_id): array
    {
        return $this->strategy->getSubscribeInfo($panel_id, $user_id);
    }

    /**
     * Обновление конфига панели
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfiguration(int $panel_id): void
    {
        $this->strategy->updateConfiguration($panel_id);
    }

    /**
     * Обновление конфига панели - стабильный вариант (без REALITY)
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfigurationStable(int $panel_id): void
    {
        $this->strategy->updateConfigurationStable($panel_id);
    }

    /**
     * Обновление конфига панели - с REALITY (лучший обход блокировок)
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfigurationReality(int $panel_id): void
    {
        $this->strategy->updateConfigurationReality($panel_id);
    }

    /**
     * Добавление пользователя панели
     *
     * @param int $panel_id
     * @param int $userTgId
     * @param int $data_limit
     * @param int $expire
     * @param string $key_activate_id
     * @return ServerUser
     * @throws GuzzleException
     */
    public function addServerUser(
        int    $panel_id,
        int    $userTgId,
        int    $data_limit,
        int    $expire,
        string $key_activate_id,
        array  $options
    ): ServerUser
    {
        return $this->strategy->addServerUser(
            $panel_id,
            $userTgId,
            $data_limit,
            $expire,
            $key_activate_id,
            $options
        );
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
        $this->strategy->checkOnline($panel_id, $user_id);
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
        $this->strategy->deleteServerUser($panel_id, $user_id);
    }

    /**
     * Обновление токена авторизации панели
     *
     * @param int $panel_id ID панели
     * @return Panel Обновленная панель
     * @throws \Exception
     */
    public function updateToken(int $panel_id): Panel
    {
        return $this->strategy->updateToken($panel_id);
    }

    /**
     * Перенос пользователя с одной панели на другую
     *
     * @param int $sourcePanel_id ID исходной панели
     * @param int $targetPanel_id ID целевой панели
     * @param string $serverUser_id ID пользователя сервера (key_activate_id)
     * @return ServerUser Обновленный пользователь сервера
     * @throws \Exception
     */
    public function transferUser(int $sourcePanel_id, int $targetPanel_id, string $serverUser_id): ServerUser
    {
        return $this->strategy->transferUser($sourcePanel_id, $targetPanel_id, $serverUser_id);
    }
}
