<?php

namespace App\Services\Panel;

use App\Models\ServerUser\ServerUser;

interface PanelInterface
{
    /**
     * Создание панели
     *
     * @param int $server_id
     * @return void
     */
    public function create(int $server_id): void;

    /**
     * Обновление конфигурации панели
     *
     * @param int $panel_id
     * @return void
     */
    public function updateConfiguration(int $panel_id): void;

    /**
     * Добавление пользователя панели
     *
     * @param int $panel_id
     * @param int $data_limit
     * @param int $expire
     * @return ServerUser
     */
    public function addServerUser(int $panel_id, int $data_limit, int $expire): ServerUser;

    /**
     * Проверка использования пользователя
     *
     * @param int $panel_id
     * @param string $user_id
     * @return void
     */
    public function checkOnline(int $panel_id, string $user_id): void;

    /**
     * Удаление пользователя панели
     *
     * @param string $user_id
     * @param int $panel_id
     * @return void
     */
    public function deleteServerUser(int $panel_id, string $user_id): void;
}
