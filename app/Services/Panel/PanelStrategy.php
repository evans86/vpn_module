<?php

namespace App\Services\Panel;

use App\Models\Panel\Panel;
use App\Models\ServerUser\ServerUser;
use App\Services\Panel\strategy\PanelMarzbanStrategy;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class PanelStrategy
{
    public PanelInterface $strategy;

    public function __construct(string $provider)
    {
        switch ($provider) {
            case Panel::MARZBAN:
                $this->strategy = new PanelMarzbanStrategy();
                break;
            default:
                throw new \DomainException('panel strategy not found');
        }
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
     * Добавление пользователя панели
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
      return $this->strategy->addServerUser($panel_id, $data_limit, $expire, $key_activate_id);
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
     * @param string $user_id
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function deleteServerUser(int $panel_id, string $user_id): void
    {
        $this->strategy->deleteServerUser($panel_id, $user_id);
    }
}
